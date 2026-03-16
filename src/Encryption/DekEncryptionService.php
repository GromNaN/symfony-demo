<?php

declare(strict_types=1);

namespace App\Encryption;

use App\Encryption\DataEncryptionKey\DataEncryptionKeyStore;
use App\Encryption\KeyManagement\KmsInterface;

final class DekEncryptionService
{
    public function __construct(
        private readonly DataEncryptionKeyStore $dataEncryptionKeyStore,
        private readonly Encryptor $encryptor,
        private readonly ?KmsInterface $kms = null,
    ) {
    }

    public function encryptRandom(string $dekId, string $plaintext): string
    {
        return $this->encryptor->encryptRandom($plaintext, $this->resolvePlainDek($dekId));
    }

    public function encryptDeterministic(string $dekId, string $plaintext): string
    {
        return $this->encryptor->encryptDeterministic($plaintext, $this->resolvePlainDek($dekId));
    }

    public function decrypt(string $dekId, string $payload): string
    {
        return $this->encryptor->decrypt($payload, $this->resolvePlainDek($dekId));
    }

    private function resolvePlainDek(string $dekId): string
    {
        $dek = $this->dataEncryptionKeyStore->getKey($dekId);

        try {
            return $dek->getPlainDek();
        } catch (\RuntimeException) {
            // The DEK is encrypted; decrypt it through KMS when available.
        }

        if ($this->kms === null) {
            throw new \RuntimeException('DEK is encrypted but no KMS implementation is configured.');
        }

        $this->kms->decrypt($dek);

        return $dek->getPlainDek();
    }
}
