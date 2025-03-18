<?php

namespace App\Entity;

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class RecipeIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: 'ingredients')]
    #[ORM\JoinColumn(nullable: false)]
    public Recipe $recipe;

    #[ORM\ManyToOne(targetEntity: Ingredient::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Ingredient $ingredient;

    #[ORM\Column(type: 'float')]
    public float $quantity;

    #[ORM\Column(length: 50)]
    public string $unit;
}
