<?php

namespace App\BridgedEntity;

use App\Entity\Recipe as RecipeEntity;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

class ObjectMapper implements ObjectMapperInterface
{
    public function map(object $source, object|string|null $target = null): object
    {
    }

    private function mapFromDto(Recipe $dto, RecipeEntity $entity): void
    {
        $entity->id = $dto->id;
        $entity->title = $dto->title;
        $entity->description = $dto->description;
        $entity->preparationTime = $dto->preparationTime;
        $entity->cookingTime = $dto->cookingTime;

        $entityIngredients = $entity->ingredients;
        $entity->ingredients = new ArrayCollection();
        foreach ($entity->ingredients as $ingredient) {
        }
    }
}
