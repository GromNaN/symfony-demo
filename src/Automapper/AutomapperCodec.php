<?php

namespace App\Automapper;

use AutoMapper\AutoMapper;
use MongoDB\BSON\Document;
use MongoDB\Codec\Codec;
use MongoDB\Codec\DecodeIfSupported;
use MongoDB\Codec\DocumentCodec;
use MongoDB\Codec\EncodeIfSupported;

/** @extends Codec<AutomapperPlane> */
class AutomapperCodec implements DocumentCodec
{
    use EncodeIfSupported;
    use DecodeIfSupported;

    public function __construct(private AutoMapper $mapper)
    {
    }

    public function canDecode($value): bool
    {
        return $value instanceof Document && $value->has('_id');
    }

    public function decode($value): AutomapperPlane
    {
        assert($this->canDecode($value));

        $value = $value->toPHP(['root' => 'array', 'document' => 'array', 'array' => 'array']);

        return $this->mapper->map($value, AutomapperPlane::class);
    }

    public function canEncode($value): bool
    {
        return $value instanceof AutomapperPlane;
    }

    /** @param AutomapperPlane $value */
    public function encode($value): Document
    {
        assert($this->canEncode($value));

        $value = $this->mapper->map($value, AutomapperPlane::class);

        return Document::fromPHP($value);
    }
}
