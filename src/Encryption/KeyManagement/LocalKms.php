<?php

declare(strict_types=1);

namespace App\Encryption\KeyManagement;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Local KMS implementation for development/testing.
 *
 * It wraps DEKs using OpenSSL (AES-256-GCM) with a key derived from MASTER_KEY_FILE.
 * Do not use in production.
 */
final class LocalKms implements KmsInterface
{
    private const MASTER_KEY_ID = 'local-master-key';
    private const CIPHER_ALGO = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private readonly string $encryptionKey;

    public function __construct(
        #[Autowire(env: 'file:MASTER_KEY_FILE')]
        private readonly string $masterKey,
    ) {
        if ($this->masterKey === '') {
            throw new \InvalidArgumentException('Master key file is empty.');
        }

        // Normalize any master key content to a 32-byte key for AES-256.
        $this->encryptionKey = hash('sha256', $this->masterKey, true);
    }

    public function encrypt(DataEncryptionKey $key): DataEncryptionKey
    {
        $plainDek = $key->getPlainDek();
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plainDek,
            self::CIPHER_ALGO,
            $this->encryptionKey,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new \RuntimeException('Failed to encrypt DEK payload with OpenSSL.');
        }

        // Payload format: IV + TAG + CIPHERTEXT
        $key->encrypt(self::MASTER_KEY_ID, $iv . $tag . $ciphertext);

        return $key;
    }

    public function decrypt(DataEncryptionKey $key): DataEncryptionKey
    {
        $payload = $key->getEncryptedDek();

        if (strlen($payload) <= self::IV_LENGTH + self::TAG_LENGTH) {
            throw new \RuntimeException('Encrypted DEK payload format is invalid.');
        }

        $iv = substr($payload, 0, self::IV_LENGTH);
        $tag = substr($payload, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($payload, self::IV_LENGTH + self::TAG_LENGTH);

        $plainDek = openssl_decrypt(
            $ciphertext,
            self::CIPHER_ALGO,
            $this->encryptionKey,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plainDek === false) {
            throw new \RuntimeException('Failed to decrypt DEK payload with local master key.');
        }

        $key->decrypt($plainDek);

        return $key;
    }
}
