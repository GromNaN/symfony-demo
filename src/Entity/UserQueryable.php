<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_queryable')]
class UserQueryable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name = '';

    // Plain values are not mapped. They are used by the QE subscriber before write.
    public ?\DateTimeInterface $birthdate = null;

    public ?int $yearlyIncome = null;

    // Queryable Encryption payload for birthdate field.
    #[ORM\Column(name: 'birthdate', type: Types::BINARY, length: 1024)]
    public string $birthdateCipher = '';

    // Queryable Encryption payload for yearly income field.
    #[ORM\Column(name: 'yearly_income', type: Types::BINARY, length: 1024, nullable: true)]
    public ?string $yearlyIncomeCipher = null;

    // Shared safe content tags for all QE fields of this row.
    // Array of binary tags (HMAC-SHA256) for equality and range queries.
    #[ORM\Column(name: 'safecontent', type: Types::SIMPLE_ARRAY)]
    public array $safeContent = [];
}
