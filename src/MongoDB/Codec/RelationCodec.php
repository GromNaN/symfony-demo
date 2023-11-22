<?php

namespace App\MongoDB\Codec;

use App\MongoDB\Document\Post;
use App\MongoDB\Repository;
use MongoDB\BSON\Document;
use MongoDB\BSON\ObjectId;
use MongoDB\Bundle\Attribute\AutowireDatabase;
use MongoDB\Client;
use MongoDB\Codec\Codec;
use MongoDB\Codec\DecodeIfSupported;
use MongoDB\Codec\DocumentCodec;
use MongoDB\Codec\EncodeIfSupported;
use MongoDB\Database;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('mongodb.codec')]
class RelationCodec implements DocumentCodec
{
    use EncodeIfSupported;
    use DecodeIfSupported;

    public function __construct(
        private readonly Repository $registry,
    ) {
    }

    public function canDecode($value): bool
    {
        return $value instanceof Document && $value->has('$ref') && $value->has('$id') && $value->get('$id') instanceof ObjectId;
    }

    public function decode($value): object
    {
        $className = 'App\\MongoDB\\Document\\'.ucfirst(substr($value['$ref'], 0, -1));

        $object = $this->registry->find($className, $value['$id']);

        if ($object === null) {
            throw new \OutOfBoundsException(sprintf('Undefined relation "%s"', $value['$ref']));
        }

        return $object;
    }

    public function canEncode($value): bool
    {
        return property_exists($value, 'id');
    }

    public function encode($value): Document
    {
        return Document::fromPHP([
            '$ref' => strtolower((new \ReflectionClass($value))->getShortName()).'s',
            '$id' => $value->id,
        ]);
    }
}
