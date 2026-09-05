<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Encryption;

/**
 * Simple key provider for testing and development.
 * In production, use a proper key management service.
 */
final class StaticKeyProvider implements KeyProviderInterface
{
    private array $keys;
    private string $defaultKeyId;

    public function __construct(array $keys = [], ?string $defaultKeyId = null)
    {
        $this->keys = $keys ?: [
            'default' => base64_encode(random_bytes(32)),
            'user-key' => base64_encode(random_bytes(32)),
            'payment-key' => base64_encode(random_bytes(32)),
        ];
        $this->defaultKeyId = $defaultKeyId ?: 'default';
    }

    public function getKey(?string $keyId = null, ?string $keyAltName = null): string
    {
        $key = $keyId ?? $keyAltName ?? $this->defaultKeyId;

        if (!isset($this->keys[$key])) {
            throw new \InvalidArgumentException("Key not found: {$key}");
        }

        return $this->keys[$key];
    }

    public function getDefaultKey(): string
    {
        return $this->getKey($this->defaultKeyId);
    }

    public function addKey(string $keyId, string $key): void
    {
        $this->keys[$keyId] = $key;
    }
}
