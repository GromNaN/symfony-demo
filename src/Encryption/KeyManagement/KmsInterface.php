<?php

namespace App\Encryption\KeyManagement;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;

interface KmsInterface
{
    /**
     * Encrypt the DEK payload and update the DataEncryptionKey object with encryptedDek.
     */
    public function encrypt(DataEncryptionKey $key): DataEncryptionKey;

    /**
     * Decrypt the DEK payload and update the DataEncryptionKey object with plainDek.
     */
    public function decrypt(DataEncryptionKey $key): DataEncryptionKey;
}
