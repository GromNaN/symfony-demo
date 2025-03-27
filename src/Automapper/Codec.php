<?php

namespace App\Automapper;

use AutoMapper\AutoMapper;
use MongoDB\BSON\Document;
use MongoDB\Codec\DecodeIfSupported;
use MongoDB\Codec\DocumentCodec;
use MongoDB\Codec\EncodeIfSupported;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class Codec implements DocumentCodec
{
    use EncodeIfSupported;
    use DecodeIfSupported;

    public function __construct(private AutoMapper $mapper, private string $class)
    {
    }

    public function canDecode($value): bool
    {
        return $value instanceof Document && $value->has('_id');
    }

    public function decode($value): object
    {
        assert($this->canDecode($value));

        $value = $value->toPHP(['root' => 'array', 'document' => 'array', 'array' => 'array']);

        return $this->mapper->map($value, $this->class);
    }

    public function canEncode($value): bool
    {
        return $value instanceof $this->class;
    }

    /** @param Plane $value */
    public function encode($value): Document
    {
        assert($this->canEncode($value));

        $value = $this->mapper->map($value, 'array');

        return Document::fromPHP($value);
    }
}
