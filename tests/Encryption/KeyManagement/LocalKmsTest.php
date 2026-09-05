<?php

declare(strict_types=1);

namespace App\Tests\Encryption\KeyManagement;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\KeyManagement\LocalKms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalKms::class)]
final class LocalKmsTest extends TestCase
{
    public function testEncryptThenDecryptRoundTrip(): void
    {
        $kms = new LocalKms('master-key-content');

        $plainDek = random_bytes(32);

        $toEncrypt = new DataEncryptionKey('dek-1', null, null, $plainDek);
        $kms->encrypt($toEncrypt);

        self::assertSame('local-master-key', $toEncrypt->getMasterKeyId());
        self::assertNotSame('', $toEncrypt->getEncryptedDek());

        $toDecrypt = new DataEncryptionKey('dek-2', 'local-master-key', $toEncrypt->getEncryptedDek());
        $kms->decrypt($toDecrypt);

        self::assertSame($plainDek, $toDecrypt->getPlainDek());
    }

    public function testDecryptFailsOnMasterKeyMismatch(): void
    {
        $kmsEncrypt = new LocalKms('key-a', 'master-key-a');
        $kmsDecrypt = new LocalKms('key-a', 'master-key-b');

        $toEncrypt = new DataEncryptionKey('dek-1', null, null, 'plain-dek');
        $kmsEncrypt->encrypt($toEncrypt);

        $toDecrypt = new DataEncryptionKey('dek-2', 'master-key-a', $toEncrypt->getEncryptedDek());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The DEK is encrypted with master key "master-key-a", but this KMS is configured for "master-key-b".');

        $kmsDecrypt->decrypt($toDecrypt);
    }

    public function testDecryptFailsWhenKeyMaterialDoesNotMatch(): void
    {
        $kmsEncrypt = new LocalKms('key-a', 'master-key-a');
        $kmsDecrypt = new LocalKms('key-b', 'master-key-a');

        $toEncrypt = new DataEncryptionKey('dek-1', null, null, 'plain-dek');
        $kmsEncrypt->encrypt($toEncrypt);

        $toDecrypt = new DataEncryptionKey('dek-2', 'master-key-a', $toEncrypt->getEncryptedDek());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decrypt DEK payload with local master key.');

        $kmsDecrypt->decrypt($toDecrypt);
    }
}
