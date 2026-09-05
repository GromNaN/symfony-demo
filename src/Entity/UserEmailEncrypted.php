<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'user_email_encrypted')]
class UserEmailEncrypted
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'user_email_encrypted')]
    public string $email;

    /** @var list<string> */
    #[ORM\Column(type: Types::SIMPLE_ARRAY)]
    public array $searchTags;
}
