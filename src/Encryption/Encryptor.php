<?php

declare(strict_types=1);

namespace App\Encryption;

/**
 * Encryptor provides low-level symmetric encryption and decryption primitives.
 */
class Encryptor
{
    private const string CIPHER_ALGO = 'aes-256-cbc';
    //private const string CIPHER_ALGO = 'aes-256-gcm';
    private const int IV_LENGTH = 16;

    public function encryptRandom(string $plaintext, string $dek): string
    {
        // Generate a fresh IV for each encryption.
        $iv = random_bytes(self::IV_LENGTH);

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER_ALGO, $dek, \OPENSSL_RAW_DATA, $iv);

        // Store IV + ciphertext so the decryptor can recover the IV later.
        return $iv . $ciphertext;
    }

    public function encryptDeterministic(string $plaintext, string $dek): string
    {
        // Derive a deterministic IV from the plaintext using SHA-512 HMAC.
        $hmac = hash_hmac('sha512', $plaintext, $dek);
        $iv = substr($hmac, 0, self::IV_LENGTH);

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER_ALGO, $dek, \OPENSSL_RAW_DATA, $iv);

        return $iv . $ciphertext;
    }

    public function decrypt(string $payload, string $dek): string
    {
        // Split the payload into IV and ciphertext.
        $iv = substr($payload, 0, self::IV_LENGTH);
        $ciphertext = substr($payload, self::IV_LENGTH);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER_ALGO, $dek, \OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            /*dump([
                'dek' => bin2hex($dek),
                'payload' => bin2hex($payload),
                'iv' => bin2hex($iv),
                'ciphertext' => bin2hex($ciphertext),
            ]);*/
            throw new \RuntimeException(sprintf('Decryption failed: %s', openssl_error_string()));
        }

        return $plaintext;
    }
}
