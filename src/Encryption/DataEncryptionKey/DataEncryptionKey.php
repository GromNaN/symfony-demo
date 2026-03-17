<?php

namespace App\Encryption\DataEncryptionKey;

/**
 * DataEncryptionKey models a DEK in either encrypted or plaintext representation.
 */
final class DataEncryptionKey implements \Stringable
{
    private ?string $masterKeyId;

    public function __construct(
        public readonly string $id,
        ?string $masterKeyId = null,
        private ?string $encryptedDek = null,
        private ?string $plainDek = null,
    ) {
        $this->masterKeyId = $masterKeyId;

        // A DEK must be created from exactly one representation.
        if (($this->encryptedDek === null) === ($this->plainDek === null)) {
            throw new \InvalidArgumentException(
                'You must provide exactly one key representation: encryptedDek or plainDek.'
            );
        }

        if ($this->encryptedDek !== null && $this->masterKeyId === null) {
            throw new \InvalidArgumentException(
                'masterKeyId is required when encryptedDek is provided.'
            );
        }
    }

    public function getMasterKeyId(): string
    {
        if ($this->masterKeyId === null) {
            throw new \RuntimeException('Master key ID is not set.');
        }

        return $this->masterKeyId;
    }

    public function getEncryptedDek(): string
    {
        if ($this->encryptedDek === null) {
            throw new \RuntimeException('Key is not encrypted.');
        }

        return $this->encryptedDek;
    }

    public function getPlainDek(): string
    {
        if ($this->plainDek === null) {
            throw new \RuntimeException('Key is not decrypted.');
        }

        return $this->plainDek;
    }

    public function encrypt(string $masterKeyId, string $encryptedDek): void
    {
        if ($this->masterKeyId !== null || $this->encryptedDek !== null) {
            throw new \RuntimeException('Encrypted key data is already set and cannot be modified.');
        }

        $this->masterKeyId = $masterKeyId;
        $this->encryptedDek = $encryptedDek;
    }

    public function decrypt(string $plainDek): void
    {
        if ($this->plainDek !== null) {
            throw new \RuntimeException('Decrypted key data is already set and cannot be modified.');
        }

        $this->plainDek = $plainDek;
    }

    public function __toString(): string
    {
        return $this->getPlainDek();
    }
}