<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Entity\Recipe as RecipeEntity;
use AutoMapper\Attribute\MapFrom;
use AutoMapper\Attribute\MapTo;

#[ApiResource(
    shortName: 'sql_recipes',
    provider: SqlRecipeState::class,
    operations: [
        new Get(uriTemplate: '/sql_recipes/{id}'),
    ]
)]
class Recipe
{
    #[MapTo(target: RecipeEntity::class, if: 'source.id ?? false')]
    public string $id;
    public string $title;
    public ?string $description;
    public int $preparationTime;
    public int $cookingTime;
    /** @var list<array{quantity: float, unit: string, name: string, description?: string}> */
    #[MapFrom(source: 'array', transformer: [SqlRecipeState::class, 'decode'])]
    public array $ingredients;
    /** @var string[] */
    #[MapFrom(source: 'array', transformer: [SqlRecipeState::class, 'decode'])]
    public array $steps;
    public string $authorName;
    /** @var array{averageRating: float, numberOfVotes: int} */
    #[MapFrom(source: 'array', transformer: [SqlRecipeState::class, 'decode'])]
    public array $popularity;
}
