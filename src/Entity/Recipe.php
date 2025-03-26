<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ApiResource(
    shortName: 'recipe_entities',
    normalizationContext: ['groups' => ['recipe:read']],
    denormalizationContext: ['groups' => ['recipe:write']],
)]
#[ORM\Entity]
#[Groups(['recipe:read', 'recipe:write'])]
class Recipe
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 255)]
    public string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: 'integer')]
    public int $preparationTime;

    #[ORM\Column(type: 'integer')]
    public int $cookingTime;

    #[ORM\OneToMany(mappedBy: 'recipe', targetEntity: RecipeIngredient::class, cascade: ['persist'], orphanRemoval: true)]
    public Collection $ingredients;

    #[ORM\OneToMany(mappedBy: 'recipe', targetEntity: Step::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['stepNumber' => 'ASC'])]
    public Collection $steps;

    #[ORM\Column(type: 'string', length: 255)]
    public string $author_name;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'recipes', cascade: ['persist', 'remove'])]
    #[Ignore]
    public ?User $author_user;

    #[ORM\OneToOne(mappedBy: 'recipe', targetEntity: Popularity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public ?Popularity $popularity = null;

    public function __construct()
    {
        $this->ingredients = new ArrayCollection();
        $this->steps = new ArrayCollection();
    }
}
