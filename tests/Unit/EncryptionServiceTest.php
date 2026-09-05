<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Tests\Unit;

use Gromnan\DoctrineEncrypt\Encryption\EncryptionService;
use Gromnan\DoctrineEncrypt\Encryption\StaticKeyProvider;
use Gromnan\DoctrineEncrypt\Mapping\Encrypted;
use PHPUnit\Framework\TestCase;

class EncryptionServiceTest extends TestCase
{
    private EncryptionService $encryptionService;
    private StaticKeyProvider $keyProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyProvider = new StaticKeyProvider([
            'test-key' => base64_encode('test-key-32-bytes-long-for-aes'),
        ]);

        $this->encryptionService = new EncryptionService($this->keyProvider);
    }

    public function testDeterministicEncryption(): void
    {
        $config = new Encrypted(
            algorithm: Encrypted::ALGORITHM_DETERMINISTIC,
            keyAltName: 'test-key'
        );

        $plaintext = 'test@example.com';

        // Encrypt the same value multiple times
        $encrypted1 = $this->encryptionService->encrypt($plaintext, $config);
        $encrypted2 = $this->encryptionService->encrypt($plaintext, $config);

        // Should produce identical ciphertext
        $this->assertSame($encrypted1, $encrypted2);
        $this->assertTrue($config->isDeterministic());
        $this->assertTrue($config->isQueryable());

        // Should decrypt back to original value
        $decrypted = $this->encryptionService->decrypt($encrypted1, $config);
        $this->assertSame($plaintext, $decrypted);
    }

    public function testRandomEncryption(): void
    {
        $config = new Encrypted(
            algorithm: Encrypted::ALGORITHM_RANDOM,
            keyAltName: 'test-key'
        );

        $plaintext = 'sensitive-data';

        // Encrypt the same value multiple times
        $encrypted1 = $this->encryptionService->encrypt($plaintext, $config);
        $encrypted2 = $this->encryptionService->encrypt($plaintext, $config);

        // Should produce different ciphertext
        $this->assertNotSame($encrypted1, $encrypted2);
        $this->assertFalse($config->isDeterministic());
        $this->assertFalse($config->isQueryable());

        // Both should decrypt back to original value
        $decrypted1 = $this->encryptionService->decrypt($encrypted1, $config);
        $decrypted2 = $this->encryptionService->decrypt($encrypted2, $config);
        $this->assertSame($plaintext, $decrypted1);
        $this->assertSame($plaintext, $decrypted2);
    }

    public function testEncryptNullValue(): void
    {
        $config = new Encrypted(keyAltName: 'test-key');

        $encrypted = $this->encryptionService->encrypt(null, $config);
        $this->assertNull($encrypted);

        $decrypted = $this->encryptionService->decrypt(null, $config);
        $this->assertNull($decrypted);
    }

    public function testEncryptDifferentDataTypes(): void
    {
        $config = new Encrypted(keyAltName: 'test-key');

        $testCases = [
            'string' => 'Hello World',
            'integer' => 42,
            'float' => 3.14159,
            'boolean' => true,
            'array' => ['key' => 'value', 'nested' => ['data']],
        ];

        foreach ($testCases as $type => $value) {
            $encrypted = $this->encryptionService->encrypt($value, $config);
            $this->assertIsString($encrypted);
            $this->assertNotEmpty($encrypted);

            $decrypted = $this->encryptionService->decrypt($encrypted, $config);
            $this->assertSame($value, $decrypted, "Failed for type: {$type}");
        }
    }

    public function testInvalidKeyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Key not found: invalid-key');

        $config = new Encrypted(keyAltName: 'invalid-key');
        $this->encryptionService->encrypt('test', $config);
    }

    public function testDecryptInvalidFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid deterministic encrypted value format');

        $config = new Encrypted(
            algorithm: Encrypted::ALGORITHM_DETERMINISTIC,
            keyAltName: 'test-key'
        );

        $this->encryptionService->decrypt('invalid-format', $config);
    }
}
