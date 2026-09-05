<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Encryption;

use Gromnan\DoctrineEncrypt\Mapping\Encrypted;
use SensitiveParameter;

/**
 * Core encryption service that handles field-level encryption/decryption.
 * Supports both deterministic and random encryption algorithms.
 */
final class EncryptionService
{
    private const CIPHER_AES_256_GCM = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    public function __construct(
        private readonly KeyProviderInterface $keyProvider
    ) {
    }

    /**
     * Encrypt a value using the specified encryption configuration
     */
    public function encrypt(#[SensitiveParameter] mixed $value, Encrypted $config): ?string
    {
        if ($value === null) {
            return null;
        }

        $plaintext = $this->serializeValue($value);
        $key = base64_decode($this->keyProvider->getKey($config->keyId, $config->keyAltName));

        if ($config->isDeterministic()) {
            return $this->encryptDeterministic($plaintext, $key);
        }

        return $this->encryptRandom($plaintext, $key);
    }

    /**
     * Decrypt an encrypted value
     */
    public function decrypt(?string $encryptedValue, Encrypted $config): mixed
    {
        if ($encryptedValue === null) {
            return null;
        }

        $key = base64_decode($this->keyProvider->getKey($config->keyId, $config->keyAltName));

        if ($config->isDeterministic()) {
            $plaintext = $this->decryptDeterministic($encryptedValue, $key);
        } else {
            $plaintext = $this->decryptRandom($encryptedValue, $key);
        }

        return $this->deserializeValue($plaintext);
    }

    /**
     * Deterministic encryption - same input always produces same output
     * Enables exact match queries but reduces security
     */
    private function encryptDeterministic(#[SensitiveParameter] string $plaintext, #[SensitiveParameter] string $key): string
    {
        // Use a fixed IV derived from the plaintext for deterministic encryption
        $iv = substr(hash('sha256', $plaintext . $key), 0, 12);
        $encrypted = openssl_encrypt($plaintext, self::CIPHER_AES_256_GCM, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false) {
            throw new \RuntimeException('Deterministic encryption failed');
        }

        return base64_encode('det:' . $iv . $tag . $encrypted);
    }

    /**
     * Random encryption - same input produces different output each time
     * Provides maximum security but no query capability
     */
    private function encryptRandom(#[SensitiveParameter] string $plaintext, #[SensitiveParameter] string $key): string
    {
        $iv = random_bytes(12);
        $encrypted = openssl_encrypt($plaintext, self::CIPHER_AES_256_GCM, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false) {
            throw new \RuntimeException('Random encryption failed');
        }

        return base64_encode('rnd:' . $iv . $tag . $encrypted);
    }

    private function decryptDeterministic(string $encryptedValue, #[SensitiveParameter] string $key): string
    {
        $decoded = base64_decode($encryptedValue);
        $prefix = substr($decoded, 0, 4);

        if ($prefix !== 'det:') {
            throw new \InvalidArgumentException('Invalid deterministic encrypted value format');
        }

        $data = substr($decoded, 4);
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, self::TAG_LENGTH);
        $encrypted = substr($data, 12 + self::TAG_LENGTH);

        $decrypted = openssl_decrypt($encrypted, self::CIPHER_AES_256_GCM, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            throw new \RuntimeException('Deterministic decryption failed');
        }

        return $decrypted;
    }

    private function decryptRandom(string $encryptedValue, #[SensitiveParameter] string $key): string
    {
        $decoded = base64_decode($encryptedValue);
        $prefix = substr($decoded, 0, 4);

        if ($prefix !== 'rnd:') {
            throw new \InvalidArgumentException('Invalid random encrypted value format');
        }

        $data = substr($decoded, 4);
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, self::TAG_LENGTH);
        $encrypted = substr($data, 12 + self::TAG_LENGTH);

        $decrypted = openssl_decrypt($encrypted, self::CIPHER_AES_256_GCM, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            throw new \RuntimeException('Random decryption failed');
        }

        return $decrypted;
    }

    private function serializeValue(#[SensitiveParameter] mixed $value): string
    {
        return match (gettype($value)) {
            'string' => 's:' . $value,
            'integer' => 'i:' . $value,
            'double' => 'd:' . $value,
            'boolean' => 'b:' . ($value ? '1' : '0'),
            default => 'j:' . json_encode($value),
        };
    }

    private function deserializeValue(string $serialized): mixed
    {
        $type = substr($serialized, 0, 2);
        $value = substr($serialized, 2);

        return match ($type) {
            's:' => $value,
            'i:' => (int) $value,
            'd:' => (float) $value,
            'b:' => $value === '1',
            'j:' => json_decode($value, true),
            default => throw new \InvalidArgumentException('Unknown serialized type: ' . $type),
        };
    }
}
