<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'user_encrypted')]
class UserEncrypted implements PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    // Deterministic encryption of the email enables unique indexing.
    #[ORM\Column(length: 512, unique: true)]
    public string $email;

    // Random encryption prevents correlating identical first names.
    #[ORM\Column(length: 512)]
    public string $firstName;

    // Random encryption prevents correlating identical last names.
    #[ORM\Column(length: 512)]
    public string $lastName;

    #[ORM\Column]
    public \DateTimeImmutable $birthday;

    // Password is hashed using Symfony's password hasher (bcrypt/argon2).
    #[ORM\Column]
    public string $password;

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
