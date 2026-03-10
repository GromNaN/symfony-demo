<?php

namespace App\Encryption\DataEncryptionKey;

class FileDataEncryptionKeyStore implements DataEncryptionKeyStore
{
    private function __construct(private string $dirname)
    {
    }

    public function getKey(string $id): DataEncryptionKey
    {
        $filepath = $this->dirname . '/' . $id;
        if (!\file_exists($filepath)) {
            throw new \RuntimeException(sprintf('The data encryption key with id "%s" does not exist.', $id));
        }

        $key = json_decode(file_get_contents($filepath), true, 512, JSON_THROW_ON_ERROR);

        return new DataEncryptionKey($key['masterKeyId'], $key['encryptedKey']);
    }
}