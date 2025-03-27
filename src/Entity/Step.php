<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[Groups(['recipe:read', 'recipe:write'])]
class Step
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: 'steps')]
    #[ORM\JoinColumn(nullable: false)]
    public Recipe $recipe;

    #[ORM\Column(type: 'integer')]
    public int $stepNumber;

    #[ORM\Column(type: 'text')]
    #[Groups(['recipe:read', 'recipe:write'])]
    public string $description;
}
