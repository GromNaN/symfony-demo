<?php

namespace App\Bson;

use ApiPlatform\Symfony\Bundle\DependencyInjection\Compiler\AttributeFilterPass;
use MongoDB\BSON\Regex;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Excluded from the auto-configuration. The definition is created by
 * the {@see AttributeFilterPass}.
 */
#[Exclude]
class SearchFilter implements FilterInterface
{
    public function __construct(private array $properties)
    {
    }

    public function apply(array $pipeline, array $context): array
    {
        if (!$filters = $context['filters'] ?? []) {
            return $pipeline;
        }

        $match = [];
        foreach ($this->properties as $property => $strategy) {
            if (!array_key_exists($property, $filters)) {
                continue;
            }

            $value = $context['filters'][$property];
            $match[$property] = match ($strategy) {
                'exact' => ['$eq' => $value],
                'partial' => ['$regex' => new Regex(preg_quote($value), 'i')],
            };
        }

        if ($match) {
            $pipeline[] = ['$match' => $match];
        }

        return $pipeline;
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
