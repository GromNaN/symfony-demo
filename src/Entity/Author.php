<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Embeddable;

#[Embeddable]
class Author
{
    #[ORM\Column(type: 'string', length: 255)]
    public string $name;

    // #[ORM\Column(name: 'user_id', type: 'integer')]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'recipes', cascade: ['persist', 'remove'])]
    public ?User $user;
}
