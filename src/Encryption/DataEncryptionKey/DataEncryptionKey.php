<?php

namespace App\Encryption\DataEncryptionKey;

final class DataEncryptionKey implements \Stringable
{
    public function __construct(
        public readonly string $masterKeyId,
        private ?string $encryptedKey = null,
        private ?string $decryptedKey = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->decryptedKey;
    }
}