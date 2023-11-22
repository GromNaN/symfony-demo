<?php

namespace App\MongoDB\Codec;

use App\MongoDB\Document\Post;
use MongoDB\BSON\Document;
use MongoDB\BSON\PackedArray;
use MongoDB\Codec\Codec;
use MongoDB\Codec\DecodeIfSupported;
use MongoDB\Codec\DocumentCodec;
use MongoDB\Codec\EncodeIfSupported;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('mongodb.document_codec')]
#[AsTaggedItem(Post::class)]
class PostCodec implements DocumentCodec
{
    use EncodeIfSupported;
    use DecodeIfSupported;

    public function __construct(
        private readonly DateTimeCodec $dateTimeCodec,
        private readonly RelationCodec $relationCodec,
        private readonly TagCodec $tagCodec,
        private readonly UserCodec $userCodec
    ) {
    }

    public function canDecode($value): bool
    {
        return $value instanceof Document;
    }

    public function decode($value): Post
    {
        return new Post(
            title: $value->title,
            slug: $value->slug,
            summary: $value->summary,
            content: $value->content,
            publishedAt: $this->dateTimeCodec->decodeIfSupported($value->publishedAt),
            author: $this->userCodec->decodeIfSupported($this->relationCodec->decodeIfSupported($value->author)),
            tags: array_map(function (Document $document) {
                return $this->tagCodec->decodeIfSupported($this->relationCodec->decodeIfSupported($document));
            }, $value->tags->toPHP(['root' => 'array', 'document' => 'bson'])),
            id: $value->_id,
        );
    }

    public function canEncode($value): bool
    {
        return $value instanceof Post;
    }

    public function encode($value): Document
    {
        return Document::fromPHP([
            '_id' => $value->id,
            'title' => $value->title,
            'slug' => $value->slug,
            'summary' => $value->summary,
            'content' => $value->content,
            'publishedAt' => $this->dateTimeCodec->encodeIfSupported($value->publishedAt),
            'author' => $this->relationCodec->encodeIfSupported($value->author),
            'tags' => array_map($this->relationCodec->encodeIfSupported(...), array_values($value->tags)),
        ]);
    }
}
