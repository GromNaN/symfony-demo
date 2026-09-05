<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Gromnan\DoctrineEncrypt\Mapping\Encrypted;
use SensitiveParameter;

/**
 * Payment entity with different encryption configurations
 */
#[ORM\Entity]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'integer')]
    public int $userId;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Encrypted(algorithm: Encrypted::ALGORITHM_RANDOM, keyAltName: 'payment-key')]
    public float $amount;

    #[ORM\Column(type: 'string', length: 20)]
    #[Encrypted(algorithm: Encrypted::ALGORITHM_DETERMINISTIC, keyAltName: 'payment-key', queryable: true)]
    public string $cardLast4;

    #[ORM\Column(type: 'string', length: 255)]
    #[Encrypted(algorithm: Encrypted::ALGORITHM_RANDOM, keyAltName: 'payment-key')]
    public string $cardNumber;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    public function __construct(int $userId, #[SensitiveParameter] float $amount, #[SensitiveParameter] string $cardNumber)
    {
        $this->userId = $userId;
        $this->amount = $amount;
        $this->cardNumber = $cardNumber;
        $this->cardLast4 = substr($cardNumber, -4);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function setAmount(#[SensitiveParameter] float $amount): void
    {
        $this->amount = $amount;
    }

    public function setCardNumber(#[SensitiveParameter] string $cardNumber): void
    {
        $this->cardNumber = $cardNumber;
        $this->cardLast4 = substr($cardNumber, -4);
    }
}
