<?php

namespace App\Entity;

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity]
class Popularity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\OneToOne(targetEntity: Recipe::class, inversedBy: 'popularity')]
    #[ORM\JoinColumn(nullable: false)]
    #[Ignore]
    public Recipe $recipe;

    #[ORM\Column(type: 'float')]
    public float $averageRating;

    #[ORM\Column(type: 'integer')]
    public int $numberOfVotes;
}
