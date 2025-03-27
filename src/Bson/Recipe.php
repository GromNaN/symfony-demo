<?php

namespace App\Bson;

use ApiPlatform\Metadata\ApiResource;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Serializable;
use MongoDB\BSON\Unserializable;

#[ApiResource(
    shortName: 'bson_recipes',
    provider: State::class,
    processor: State::class,
)]
class Recipe implements Serializable, Unserializable
{
    public string $id;
    public string $title;
    public ?string $description;
    public int $preparationTime;
    public int $cookingTime;
    public array $ingredients;
    public array $steps;
    public string $authorName;
    public array $popularity;

    public function bsonSerialize(): array
    {
        return [
            '_id' => new ObjectId($this->id),
            'title' => $this->title,
            'description' => $this->description,
            'preparationTime' => $this->preparationTime,
            'cookingTime' => $this->cookingTime,
            'ingredients' => $this->ingredients,
            'steps' => $this->steps,
            'authorName' => $this->authorName ?? '',
            'popularity' => $this->popularity,
        ];
    }

    public function bsonUnserialize(array $data): void
    {
        $this->id = (string) $data['_id'];
        $this->title = $data['title'];
        $this->description = $data['description'];
        $this->preparationTime = $data['preparationTime'];
        $this->cookingTime = $data['cookingTime'];
        $this->ingredients = $data['ingredients'];
        $this->steps = $data['steps'];
        $this->authorName = $data['author_name'] ?? '';
        $this->popularity = (array) $data['popularity'];
    }
}
