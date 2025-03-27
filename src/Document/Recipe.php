<?php

namespace App\Document;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'recipe_documents',
    normalizationContext: ['groups' => ['recipe:read']],
    denormalizationContext: ['groups' => ['recipe:write']],
)]
#[ODM\Document(collection: 'recipes')]
#[Groups(['recipe:read', 'recipe:write'])]
class Recipe
{
    #[ODM\Id]
    #[Groups(['recipe:read', 'recipe:write'])]
    public string $id;

    #[ODM\Field(type: 'string')]
    #[Groups(['recipe:read', 'recipe:write'])]
    public string $title;

    #[ODM\Field(type: 'string')]
    #[Groups(['recipe:read', 'recipe:write'])]
    public ?string $description = null;

    #[ODM\Field(type: 'int')]
    #[Groups(['recipe:read', 'recipe:write'])]
    public int $preparationTime;

    #[ODM\Field(type: 'int')]
    #[Groups(['recipe:read', 'recipe:write'])]
    public int $cookingTime;

    /** @var Collection<Ingredient> */
    #[ODM\EmbedMany(targetDocument: Ingredient::class)]
    #[Groups(['recipe:read', 'recipe:write'])]
    public Collection $ingredients;

    /** @var Collection<Step> */
    #[ODM\EmbedMany(targetDocument: Step::class)]
    #[Groups(['recipe:read', 'recipe:write'])]
    public Collection $steps;

    #[ODM\EmbedOne(targetDocument: Author::class)]
    #[Groups(['recipe:read', 'recipe:write'])]
    public Author $author;

    #[ODM\EmbedOne(targetDocument: Popularity::class)]
    #[Groups(['recipe:read', 'recipe:write'])]
    public ?Popularity $popularity = null;

    public function __construct()
    {
        $this->steps = new ArrayCollection();
        $this->ingredients = new ArrayCollection();
    }
}
