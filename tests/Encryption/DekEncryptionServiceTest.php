<?php

declare(strict_types=1);

namespace App\Tests\Encryption;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\DataEncryptionKey\DataEncryptionKeyStore;
use App\Encryption\DekEncryptionService;
use App\Encryption\Encryptor;
use App\Encryption\KeyManagement\KmsInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DekEncryptionService::class)]
final class DekEncryptionServiceTest extends TestCase
{
    public function testUsesPlainDekWithoutCallingKms(): void
    {
        $dek = random_bytes(32);

        $store = new class($dek) implements DataEncryptionKeyStore {
            public function __construct(private readonly string $dek)
            {
            }

            public function getKey(string $id): DataEncryptionKey
            {
                return new DataEncryptionKey($id, null, null, $this->dek);
            }
        };

        $kms = new class implements KmsInterface {
            public int $decryptCalls = 0;

            public function encrypt(DataEncryptionKey $key): DataEncryptionKey
            {
                return $key;
            }

            public function decrypt(DataEncryptionKey $key): DataEncryptionKey
            {
                ++$this->decryptCalls;

                return $key;
            }
        };

        $service = new DekEncryptionService($store, new Encryptor(), $kms);

        $payload = $service->encryptRandom('default', 'hello');
        self::assertSame('hello', $service->decrypt('default', $payload));
        self::assertSame(0, $kms->decryptCalls);
    }

    public function testDecryptsDekThroughKmsWhenOnlyEncryptedDekIsAvailable(): void
    {
        $dek = random_bytes(32);

        $store = new class implements DataEncryptionKeyStore {
            public function getKey(string $id): DataEncryptionKey
            {
                return new DataEncryptionKey($id, 'master-1', 'vault:v1:encrypted-dek');
            }
        };

        $kms = new class($dek) implements KmsInterface {
            public int $decryptCalls = 0;

            public function __construct(private readonly string $dek)
            {
            }

            public function encrypt(DataEncryptionKey $key): DataEncryptionKey
            {
                return $key;
            }

            public function decrypt(DataEncryptionKey $key): DataEncryptionKey
            {
                ++$this->decryptCalls;
                $key->decrypt($this->dek);

                return $key;
            }
        };

        $service = new DekEncryptionService($store, new Encryptor(), $kms);

        $payload = $service->encryptDeterministic('default', 'hello');
        self::assertSame('hello', $service->decrypt('default', $payload));
        self::assertSame(2, $kms->decryptCalls);
    }

    public function testThrowsWhenDekIsEncryptedAndNoKmsIsConfigured(): void
    {
        $store = new class implements DataEncryptionKeyStore {
            public function getKey(string $id): DataEncryptionKey
            {
                return new DataEncryptionKey($id, 'master-1', 'vault:v1:encrypted-dek');
            }
        };

        $service = new DekEncryptionService($store, new Encryptor());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DEK is encrypted but no KMS implementation is configured.');

        $service->encryptRandom('default', 'hello');
    }
}

