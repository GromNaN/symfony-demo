<?php

namespace App\Automapper;

use ApiPlatform\Metadata\ApiResource;
use App\Bson\State;

#[ApiResource(
    shortName: 'automapper_recipes',
    provider: State::class,
    processor: State::class,
)]
class Recipe
{
    public string $id;
    public string $title;
    public ?string $description;
    public int $preparationTime;
    public int $cookingTime;
    public array $ingredients;
    public array $steps;
    public array $popularity;
}
