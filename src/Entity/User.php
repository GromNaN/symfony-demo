<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'user')]
class User implements PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    // Deterministic encryption of the email enables unique indexing.
    #[ORM\Column(length: 512, unique: true, type: Types::BINARY)]
    public string $email;

    // Random encryption prevents correlating identical first names.
    #[ORM\Column(length: 512, type: Types::BINARY)]
    public string $firstName;

    // Random encryption prevents correlating identical last names.
    #[ORM\Column(length: 512, type: Types::BINARY)]
    public string $lastName;

    // Password is hashed using Symfony's password hasher (bcrypt/argon2).
    #[ORM\Column]
    public string $password;

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
