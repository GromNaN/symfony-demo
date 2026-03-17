<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class UserQe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public ?int $id = null;

    // Plain values are not mapped. They are used by the QE subscriber before write.
    public ?\DateTimeInterface $birthdate = null;

    public ?int $yearlyIncome = null;

    // Queryable Encryption payload for birthdate field.
    #[ORM\Column(name: 'birthdate_cipher', type: Types::BINARY, length: 1024)]
    public string $birthdateCipher = '';

    // Queryable Encryption payload for yearly income field.
    #[ORM\Column(name: 'yearly_income_cipher', type: Types::BINARY, length: 1024, nullable: true)]
    public ?string $yearlyIncomeCipher = null;

    // Shared safe content tags for all QE fields of this row.
    #[ORM\Column(name: 'safecontent', type: Types::JSON)]
    public array $safeContent = [];

    /** @var Collection<int, UsersEsc> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UsersEsc::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    public Collection $escEntries;

    public function __construct()
    {
        $this->escEntries = new ArrayCollection();
    }

    /**
     * @return list<UsersEsc>
     */
    public function clearEscEntriesForField(int $fieldId): array
    {
        $removed = [];

        foreach ($this->escEntries as $entry) {
            if ($entry->getFieldId() === $fieldId) {
                $removed[] = $entry;
                $this->escEntries->removeElement($entry);
            }
        }

        return $removed;
    }

    public function addEscEntry(UsersEsc $esc): void
    {
        if (! $this->escEntries->contains($esc)) {
            $this->escEntries->add($esc);
        }

        $esc->setUser($this);
    }
}
