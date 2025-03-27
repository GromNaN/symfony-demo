<?php

namespace App\Bson;

use ApiPlatform\Symfony\Bundle\DependencyInjection\Compiler\AttributeFilterPass;
use MongoDB\BSON\Regex;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Excluded from the autoconfiguration. The definition is created by
 * the {@see AttributeFilterPass}.
 */
#[Exclude]
class SearchFilter implements FilterInterface
{
    public function __construct(private array $properties)
    {
    }

    public function apply(array $query = [], array $context = []): array
    {
        if (!$filters = $context['filters'] ?? []) {
            return $query;
        }

        foreach ($this->properties as $property => $strategy) {
            if (!array_key_exists($property, $filters)) {
                continue;
            }

            $value = $filters[$property];
            $query[$property] = match ($strategy) {
                'exact' => ['$eq' => $value],
                'partial' => ['$regex' => new Regex(preg_quote($value), 'i')],
            };
        }

        return $query;
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
