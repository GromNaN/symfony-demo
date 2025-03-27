<?php

namespace App\Entity;

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
class Popularity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['recipe:read', 'recipe:write'])]
    public ?int $id = null;

    #[ORM\OneToOne(targetEntity: Recipe::class, inversedBy: 'popularity')]
    #[ORM\JoinColumn(nullable: false)]
    public Recipe $recipe;

    #[ORM\Column(type: 'float')]
    #[Groups(['recipe:read', 'recipe:write'])]
    public float $averageRating;

    #[ORM\Column(type: 'integer')]
    #[Groups(['recipe:read', 'recipe:write'])]
    public int $numberOfVotes;
}
