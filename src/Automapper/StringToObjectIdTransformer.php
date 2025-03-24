<?php

namespace App\Automapper;

use AutoMapper\Metadata\MapperMetadata;
use AutoMapper\Metadata\SourcePropertyMetadata;
use AutoMapper\Metadata\TargetPropertyMetadata;
use AutoMapper\Metadata\TypesMatching;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformerInterface;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformerSupportInterface;

class ObjectIdTransformer implements PropertyTransformerInterface, PropertyTransformerSupportInterface
{
    public function transform(mixed $value, object|array $source, array $context): mixed
    {
        // TODO: Implement transform() method.
    }

    public function supports(TypesMatching $types, SourcePropertyMetadata $source, TargetPropertyMetadata $target, MapperMetadata $mapperMetadata): bool
    {
        // TODO: Implement supports() method.
    }
}