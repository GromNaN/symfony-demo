<?php

declare(strict_types=1);

namespace App\Tests\Encryption\DataEncryptionKey;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataEncryptionKey::class)]
final class DataEncryptionKeyTest extends TestCase
{
    public function testCanBeInstantiatedWithEncryptedDekOnly(): void
    {
        $dek = new DataEncryptionKey('dek-1', 'master-1', 'encrypted-value');

        self::assertSame('dek-1', $dek->id);
        self::assertSame('master-1', $dek->getMasterKeyId());
        self::assertSame('encrypted-value', $dek->getEncryptedDek());
    }

    public function testCanBeInstantiatedWithPlainDekOnly(): void
    {
        $dek = new DataEncryptionKey('dek-1', null, null, 'plain-value');

        self::assertSame('dek-1', $dek->id);
        self::assertSame('plain-value', $dek->getPlainDek());
    }

    public function testGetEncryptedDekThrowsWhenOnlyPlainExists(): void
    {
        $dek = new DataEncryptionKey('dek-1', null, null, 'plain-value');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Key is not encrypted.');

        $dek->getEncryptedDek();
    }

    public function testGetPlainDekThrowsWhenOnlyEncryptedExists(): void
    {
        $dek = new DataEncryptionKey('dek-1', 'master-1', 'encrypted-value');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Key is not decrypted.');

        $dek->getPlainDek();
    }

    public function testThrowsWhenMasterKeyIdMissingForEncryptedRepresentation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('masterKeyId is required when encryptedDek is provided.');

        new DataEncryptionKey('dek-1', null, 'encrypted-value');
    }

    public function testThrowsWhenNeitherRepresentationIsProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DataEncryptionKey('dek-1');
    }

    public function testThrowsWhenBothRepresentationsAreProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DataEncryptionKey('dek-1', 'master-1', 'encrypted-value', 'plain-value');
    }

    public function testEncryptSetsMasterKeyIdAndEncryptedDekOnce(): void
    {
        $dek = new DataEncryptionKey('dek-1', null, null, 'plain-value');

        $dek->encrypt('master-1', 'encrypted-value');

        self::assertSame('master-1', $dek->getMasterKeyId());
        self::assertSame('encrypted-value', $dek->getEncryptedDek());
    }

    public function testEncryptThrowsWhenEncryptedDataAlreadyExists(): void
    {
        $dek = new DataEncryptionKey('dek-1', 'master-1', 'encrypted-value');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encrypted key data is already set and cannot be modified.');

        $dek->encrypt('master-2', 'encrypted-value-2');
    }

    public function testDecryptSetsPlainDekOnce(): void
    {
        $dek = new DataEncryptionKey('dek-1', 'master-1', 'encrypted-value');

        $dek->decrypt('plain-value');

        self::assertSame('plain-value', $dek->getPlainDek());
    }

    public function testDecryptThrowsWhenPlainDataAlreadyExists(): void
    {
        $dek = new DataEncryptionKey('dek-1', null, null, 'plain-value');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decrypted key data is already set and cannot be modified.');

        $dek->decrypt('plain-value-2');
    }
}
