<?php

declare(strict_types=1);

namespace App\Tests\Encryption;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\DataEncryptionKey\DataEncryptionKeyStore;
use App\Encryption\DekEncryptionService;
use App\Encryption\EncryptedType;
use App\Encryption\Encryptor;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\StringType;
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
        $type = new EncryptedType(new StringType(), $this->createDekService(random_bytes(32)), 'default', true);

        $databaseValue = $type->convertToDatabaseValue('john@example.test', $this->platform);
        $phpValue = $type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame('john@example.test', $phpValue);
    }

    public function testRandomRoundTrip(): void
    {
        $type = new EncryptedType(new StringType(), $this->createDekService(random_bytes(32)), 'default', false);

        $databaseValue = $type->convertToDatabaseValue('Jane', $this->platform);
        $phpValue = $type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame('Jane', $phpValue);
    }

    public function testDeterministicProducesSameCiphertext(): void
    {
        $dek = random_bytes(32);
        $type = new EncryptedType(new StringType(), $this->createDekService($dek), 'default', true);

        $first = $type->convertToDatabaseValue('same-value', $this->platform);
        $second = $type->convertToDatabaseValue('same-value', $this->platform);

        self::assertSame($first, $second);
    }

    public function testRandomProducesDifferentCiphertext(): void
    {
        $dek = random_bytes(32);
        $type = new EncryptedType(new StringType(), $this->createDekService($dek), 'default', false);

        $first = $type->convertToDatabaseValue('same-value', $this->platform);
        $second = $type->convertToDatabaseValue('same-value', $this->platform);

        self::assertNotSame($first, $second);
    }

    private function createDekService(string $dek): DekEncryptionService
    {
        $store = new class($dek) implements DataEncryptionKeyStore {
            public function __construct(private readonly string $dek)
            {
            }

            public function getKey(string $id): DataEncryptionKey
            {
                return new DataEncryptionKey($id, null, null, $this->dek);
            }
        };

        return new DekEncryptionService($store, new Encryptor());
    }
}
