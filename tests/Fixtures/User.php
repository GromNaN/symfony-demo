<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Gromnan\DoctrineEncrypt\Mapping\Encrypted;
use SensitiveParameter;

/**
 * User entity inspired by MongoDB queryable encryption example
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    public string $name;

    #[ORM\Column(type: 'string', length: 255)]
    #[Encrypted(algorithm: Encrypted::ALGORITHM_DETERMINISTIC, keyAltName: 'user-key', queryable: true)]
    public string $email;

    #[ORM\Column(type: 'string', length: 255)]
    #[Encrypted(algorithm: Encrypted::ALGORITHM_RANDOM, keyAltName: 'user-key')]
    public string $ssn;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Encrypted(algorithm: Encrypted::ALGORITHM_DETERMINISTIC, keyAltName: 'user-key', queryable: true)]
    public ?string $bloodType = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Encrypted(algorithm: Encrypted::ALGORITHM_RANDOM, keyAltName: 'user-key')]
    public ?array $medicalRecords = null;

    public function __construct(string $name, #[SensitiveParameter] string $email, #[SensitiveParameter] string $ssn)
    {
        $this->name = $name;
        $this->email = $email;
        $this->ssn = $ssn;
    }

    public function setEmail(#[SensitiveParameter] string $email): void
    {
        $this->email = $email;
    }

    public function setSsn(#[SensitiveParameter] string $ssn): void
    {
        $this->ssn = $ssn;
    }

    public function setBloodType(#[SensitiveParameter] ?string $bloodType): void
    {
        $this->bloodType = $bloodType;
    }

    public function setMedicalRecords(#[SensitiveParameter] ?array $medicalRecords): void
    {
        $this->medicalRecords = $medicalRecords;
    }
}
