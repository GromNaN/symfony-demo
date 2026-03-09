<?php

declare(strict_types=1);

namespace App\Tests\Encryption;

use App\Encryption\EncryptedType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\StringType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncryptedType::class)]
final class EncryptedTypeTest extends TestCase
{
    private SQLitePlatform $platform;

    protected function setUp(): void
    {
        $this->platform = new SQLitePlatform();
    }

    public function testDeterministicRoundTrip(): void
    {
        $type = new EncryptedType(new StringType(), random_bytes(32), true);

        $databaseValue = $type->convertToDatabaseValue('john@example.test', $this->platform);
        $phpValue = $type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame('john@example.test', $phpValue);
    }

    public function testRandomRoundTrip(): void
    {
        $type = new EncryptedType(new StringType(), random_bytes(32), false);

        $databaseValue = $type->convertToDatabaseValue('Jane', $this->platform);
        $phpValue = $type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame('Jane', $phpValue);
    }

    public function testDeterministicProducesSameCiphertext(): void
    {
        $dek = random_bytes(32);
        $type = new EncryptedType(new StringType(), $dek, true);

        $first = $type->convertToDatabaseValue('same-value', $this->platform);
        $second = $type->convertToDatabaseValue('same-value', $this->platform);

        self::assertSame($first, $second);
    }

    public function testRandomProducesDifferentCiphertext(): void
    {
        $dek = random_bytes(32);
        $type = new EncryptedType(new StringType(), $dek, false);

        $first = $type->convertToDatabaseValue('same-value', $this->platform);
        $second = $type->convertToDatabaseValue('same-value', $this->platform);

        self::assertNotSame($first, $second);
    }

    public function testAcceptsHexDek(): void
    {
        $dekHex = bin2hex(random_bytes(32));
        $type = new EncryptedType(new StringType(), $dekHex, true);

        $databaseValue = $type->convertToDatabaseValue('hello', $this->platform);
        $phpValue = $type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame('hello', $phpValue);
    }

    public function testInvalidDekThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EncryptedType(new StringType(), 'short-key', true);
    }
}
