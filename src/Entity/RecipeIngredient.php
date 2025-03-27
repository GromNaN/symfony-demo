<?php

namespace App\Entity;

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[Groups(['recipe:read', 'recipe:write'])]
class RecipeIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: 'ingredients')]
    #[ORM\JoinColumn(nullable: false)]
    public Recipe $recipe;

    #[ORM\ManyToOne(targetEntity: Ingredient::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['recipe:read', 'recipe:write'])]
    public Ingredient $ingredient;

    #[ORM\Column(type: 'float')]
    #[Groups(['recipe:read', 'recipe:write'])]
    public float $quantity;

    #[ORM\Column(length: 50)]
    #[Groups(['recipe:read', 'recipe:write'])]
    public string $unit;
}
