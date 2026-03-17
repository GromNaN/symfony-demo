<?php

declare(strict_types=1);

namespace App\Entity;

use App\Encryption\QueryableEncryption\EscRowDescriptor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users_esc')]
#[ORM\Index(name: 'idx_users_esc_field_tag', columns: ['field_id', 'range_tag'])]
#[ORM\Index(name: 'idx_users_esc_field_bounds', columns: ['field_id', 'value_lower', 'value_upper'])]
class UsersEsc
{
    public const int FIELD_BIRTHDATE = 1;
    public const int FIELD_YEARLY_INCOME = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserQe::class, inversedBy: 'escEntries')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public UserQe $user;

    #[ORM\Column(name: 'field_id', type: Types::SMALLINT)]
    public int $fieldId;

    #[ORM\Column(name: 'range_tag', type: Types::BINARY, length: 1024)]
    public string $rangeTag;

    #[ORM\Column(name: 'value_lower', type: Types::BIGINT)]
    public string $valueLower;

    #[ORM\Column(name: 'value_upper', type: Types::BIGINT)]
    public string $valueUpper;

    public function setUser(UserQe $user): void
    {
        $this->user = $user;
    }

    public function getFieldId(): int
    {
        return $this->fieldId;
    }

    public function setFromDescriptor(EscRowDescriptor $descriptor): void
    {
        $this->fieldId = $descriptor->fieldId;
        $this->rangeTag = $descriptor->rangeTag;
        $this->valueLower = (string) $descriptor->valueLower;
        $this->valueUpper = (string) $descriptor->valueUpper;
    }
}
