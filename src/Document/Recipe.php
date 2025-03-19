<?php

namespace App\Document;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ApiResource(shortName: 'odm_recipe')]
#[ODM\Document]
class Recipe
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field(type: 'string')]
    public string $title;

    #[ODM\Field(type: 'string')]
    public ?string $description = null;

    #[ODM\Field(type: 'int')]
    public int $preparationTime;

    #[ODM\Field(type: 'int')]
    public int $cookingTime;

    /** @var Collection<Ingredient> */
    #[ODM\EmbedMany(targetDocument: Ingredient::class)]
    public Collection $ingredients;

    /** @var Collection<Step> */
    #[ODM\EmbedMany(targetDocument: Step::class)]
    public Collection $steps;

    #[ODM\EmbedOne(targetDocument: Author::class)]
    public Author $author;

    #[ODM\EmbedOne(targetDocument: Popularity::class)]
    public ?Popularity $popularity = null;

    public function __construct()
    {
        $this->steps = new ArrayCollection();
        $this->ingredients = new ArrayCollection();
    }
}
