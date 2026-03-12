<?php

declare(strict_types=1);

namespace App\Tests\Encryption\KeyManagement;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\KeyManagement\KmsInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataEncryptionKey::class)]
final class KmsInterfaceTest extends TestCase
{
    public function testEncryptAndDecryptUpdateDataEncryptionKey(): void
    {
        $kms = new class implements KmsInterface {
            public function encrypt(DataEncryptionKey $key): DataEncryptionKey
            {
                $plain = $key->getPlainDek();
                $key->encrypt('master-1', base64_encode($plain));

                return $key;
            }

            public function decrypt(DataEncryptionKey $key): DataEncryptionKey
            {
                $encrypted = $key->getEncryptedDek();
                $plain = base64_decode($encrypted, true) ?: '';
                $key->decrypt($plain);

                return $key;
            }
        };

        $plainKey = new DataEncryptionKey('dek-1', null, null, 'dek-plaintext');
        $kms->encrypt($plainKey);

        self::assertSame('master-1', $plainKey->getMasterKeyId());
        self::assertSame(base64_encode('dek-plaintext'), $plainKey->getEncryptedDek());

        $encryptedKey = new DataEncryptionKey('dek-2', 'master-1', base64_encode('dek-plaintext'));
        $kms->decrypt($encryptedKey);

        self::assertSame('dek-plaintext', $encryptedKey->getPlainDek());
        self::assertSame('dek-plaintext', (string) $encryptedKey);
    }
}
