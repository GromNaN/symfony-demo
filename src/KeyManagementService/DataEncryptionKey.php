<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\KeyManagementService;

use SensitiveParameter;

/**
 * Represents a Data Encryption Key with both plaintext and encrypted versions
 */
final readonly class DataEncryptionKey
{
    public function __construct(
        public string $keyId,
        #[SensitiveParameter] public string $plaintextKey,
        public string $encryptedKey,
        public string $masterKeyId,
        public array $encryptionContext = [],
        public ?\DateTimeImmutable $createdAt = null,
        public ?int $version = null
    ) {
    }

    /**
     * Get the key length in bytes
     */
    public function getKeyLength(): int
    {
        return strlen(base64_decode($this->plaintextKey));
    }

    /**
     * Check if this DEK was encrypted with a specific master key
     */
    public function isEncryptedWith(string $masterKeyId): bool
    {
        return $this->masterKeyId === $masterKeyId;
    }

    /**
     * Create a new instance with sensitive data cleared
     */
    public function withoutPlaintextKey(): self
    {
        return new self(
            keyId: $this->keyId,
            plaintextKey: '',
            encryptedKey: $this->encryptedKey,
            masterKeyId: $this->masterKeyId,
            encryptionContext: $this->encryptionContext,
            createdAt: $this->createdAt,
            version: $this->version
        );
    }
}
