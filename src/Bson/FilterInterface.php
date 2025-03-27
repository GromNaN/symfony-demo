<?php

namespace App\Bson;

use ApiPlatform\Metadata\FilterInterface as BaseFilterInterface;

interface FilterInterface extends BaseFilterInterface
{
    public function apply(array $query, array $context): array;
}
