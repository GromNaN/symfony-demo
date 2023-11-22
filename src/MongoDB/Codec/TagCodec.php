<?php

namespace App\MongoDB\Codec;

use App\MongoDB\Document\Tag;
use MongoDB\BSON\Document;
use MongoDB\Codec\DecodeIfSupported;
use MongoDB\Codec\DocumentCodec;
use MongoDB\Codec\EncodeIfSupported;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mongodb.document_codec')]
#[AsTaggedItem(Tag::class)]
class TagCodec implements DocumentCodec
{
    use EncodeIfSupported;
    use DecodeIfSupported;

    public function canDecode($value): bool
    {
        return $value instanceof Document;
    }

    public function decode($value): Tag
    {
        return new Tag(
            name: $value->name,
        );
    }

    public function canEncode($value): bool
    {
        return $value instanceof Tag;
    }

    public function encode($value): Document
    {
        assert($value instanceof Tag, sprintf('Expected instance of %s, got %s', Tag::class, get_debug_type($value)));
        return Document::fromPHP([
            '_id' => $value->id,
            'name' => $value->name,
        ]);
    }
}
