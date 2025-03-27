<?php

namespace App\Automapper;

use AutoMapper\Metadata\MapperMetadata;
use AutoMapper\Metadata\SourcePropertyMetadata;
use AutoMapper\Metadata\TargetPropertyMetadata;
use AutoMapper\Metadata\TypesMatching;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformerInterface;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformerSupportInterface;
use MongoDB\BSON\ObjectId;

class StringToObjectIdTransformer
{
}

/*
class StringToObjectIdTransformer implements PropertyTransformerInterface, PropertyTransformerSupportInterface
{
    public function transform(mixed $value, object|array $source, array $context): mixed
    {
        return new ObjectId($value);
    }

    public function supports(TypesMatching $types, SourcePropertyMetadata $source, TargetPropertyMetadata $target, MapperMetadata $mapperMetadata): bool
    {
        return $target->property === '_id';
    }
}
*/
