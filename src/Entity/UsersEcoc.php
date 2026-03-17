<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users_ecoc')]
#[ORM\Index(name: 'idx_users_ecoc_field_epoch', columns: ['field_id', 'compaction_epoch'])]
class UsersEcoc
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UsersEsc::class)]
    #[ORM\JoinColumn(name: 'esc_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public UsersEsc $escEntry;

    #[ORM\Column(name: 'field_id', type: Types::SMALLINT)]
    public int $fieldId;

    // Bigint is represented as string by DBAL for portability.
    #[ORM\Column(name: 'compaction_epoch', type: Types::BIGINT)]
    public string $compactionEpoch;

    #[ORM\Column(name: 'is_compacted', type: Types::BOOLEAN)]
    public bool $isCompacted = false;
}

