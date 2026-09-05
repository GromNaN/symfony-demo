<?php

declare(strict_types=1);

namespace App\Encryption\KeyManagement;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * LocalKms wraps DEKs locally with OpenSSL and a master key sourced from a file.
 * Do not use in production.
 */
final class LocalKms implements KmsInterface
{
    private const DEFAULT_MASTER_KEY_ID = 'local-master-key';
    private const CIPHER_ALGO = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private readonly string $encryptionKey;

    public function __construct(
        #[Autowire(env: 'file:MASTER_KEY_FILE')]
        private readonly string $masterKey,
        private readonly string $masterKeyId = self::DEFAULT_MASTER_KEY_ID,
    ) {
        if ($this->masterKey === '') {
            throw new \InvalidArgumentException('Master key file is empty.');
        }

        if ($this->masterKeyId === '') {
            throw new \InvalidArgumentException('Master key ID cannot be empty.');
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
        $key->encrypt($this->masterKeyId, $iv . $tag . $ciphertext);

        return $key;
    }

    public function decrypt(DataEncryptionKey $key): DataEncryptionKey
    {
        if ($key->getMasterKeyId() !== $this->masterKeyId) {
            throw new \RuntimeException(sprintf(
                'The DEK is encrypted with master key "%s", but this KMS is configured for "%s".',
                $key->getMasterKeyId(),
                $this->masterKeyId
            ));
        }

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
