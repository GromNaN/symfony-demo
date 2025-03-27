<?php

namespace App\BridgedEntity;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use App\Entity\Recipe as RecipeEntity;
use AutoMapper\Attribute\MapTo;

#[ApiResource(
    shortName: 'bridged_recipe_entities',
    provider: State::class,
    processor: State::class,
    stateOptions: new Options(
        entityClass: RecipeEntity::class,
    ),
)]
class Recipe
{
    #[MapTo(target: RecipeEntity::class, if: 'source.id ?? false')]
    public string $id;
    public string $title;
    public ?string $description;
    public int $preparationTime;
    public int $cookingTime;
    public array $ingredients;

    /** @var string[] */
    public array $steps;
    public string $author_name;
    /** @var array{averageRating: float, numberOfVotes: int} */
    public array $popularity;
}
