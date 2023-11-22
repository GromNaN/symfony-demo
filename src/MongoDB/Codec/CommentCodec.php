<?php

namespace App\MongoDB\Codec;

use App\MongoDB\Document\Comment;
use MongoDB\BSON\Document;
use MongoDB\Codec\DecodeIfSupported;
use MongoDB\Codec\DocumentCodec;
use MongoDB\Codec\EncodeIfSupported;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mongodb.document_codec')]
#[AsTaggedItem(Comment::class)]
class CommentCodec implements DocumentCodec
{
    use EncodeIfSupported;
    use DecodeIfSupported;

    public function __construct(
        private readonly RelationCodec $relationCodec,
        private readonly DateTimeCodec $dateTimeCodec,
    )
    {
    }

    public function canDecode($value): bool
    {
        return $value instanceof Document;
    }

    public function decode($value): Comment
    {
        assert($value instanceof Document, sprintf('Expected instance of %s, got %s', Document::class, get_debug_type($value)));

        return new Comment(
            content: $value->content,
            post: $this->relationCodec->decodeIfSupported($value->post),
            author: $this->relationCodec->decodeIfSupported($value->author),
            id: $value->_id,
            publishedAt: $this->dateTimeCodec->decodeIfSupported($value->publishedAt),
        );
    }

    public function canEncode($value): bool
    {
        return $value instanceof Comment;
    }

    public function encode($value): Document
    {
        assert($value instanceof Comment, sprintf('Expected instance of %s, got %s', Comment::class, get_debug_type($value)));

        return Document::fromPHP([
            '_id' => $value->id,
            'content' => $value->content,
            'post' => $this->relationCodec->encodeIfSupported($value->post),
            'author' => $this->relationCodec->encodeIfSupported($value->author),
            'publishedAt' => $this->dateTimeCodec->encodeIfSupported($value->publishedAt),
        ]);
    }
}
