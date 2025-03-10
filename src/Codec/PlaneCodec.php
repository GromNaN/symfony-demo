<?php

namespace App\Codec;

use MongoDB\BSON\Document;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Codec\Codec;
use MongoDB\Codec\DecodeIfSupported;
use MongoDB\Codec\DocumentCodec;
use MongoDB\Codec\EncodeIfSupported;

/** @extends Codec<CodecPlane> */
class PlaneCodec implements DocumentCodec
{
    use EncodeIfSupported;
    use DecodeIfSupported;

    public function canDecode($value): bool
    {
        return $value instanceof Document && $value->has('_id');
    }

    public function decode($value): CodecPlane
    {
        assert($this->canDecode($value));

        $plane = new CodecPlane();
        $plane->id = new ObjectId($value->get('_id'));
        $plane->name = $value->get('name');
        $plane->createdAt = $value->get('created_at')->toDateTimeImmutable();

        return $plane;
    }

    public function canEncode($value): bool
    {
        return $value instanceof CodecPlane;
    }

    /** @param CodecPlane $value */
    public function encode($value): Document
    {
        assert($this->canEncode($value));

        // BSON writer would avoid creating an array and directly write the values to the buffer
        return Document::fromPHP([
            '_id' => new ObjectId($value->id ?? null),
            'name' => $value->name,
            'created_at' => new UTCDateTime($value->createdAt),
        ]);
    }
}
