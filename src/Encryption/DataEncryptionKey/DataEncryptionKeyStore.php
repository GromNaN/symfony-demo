<?php

namespace App\Encryption\DataEncryptionKey;

interface DataEncryptionKeyStore
{
    public function getKey(string $id): DataEncryptionKey;
}
