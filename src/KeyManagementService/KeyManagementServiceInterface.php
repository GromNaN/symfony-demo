<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\KeyManagementService;

use SensitiveParameter;

/**
 * Interface for Key Management Service implementations.
 * Handles master key operations and Data Encryption Key (DEK) lifecycle.
 */
interface KeyManagementServiceInterface
{
    /**
     * Create a new master key for encrypting/decrypting DEKs
     *
     * @param string $keyId Unique identifier for the master key
     * @param array<string, mixed> $metadata Optional metadata for the key
     * @return string The master key ARN/ID/URI depending on the implementation
     * @throws KeyManagementException
     */
    public function createMasterKey(string $keyId, array $metadata = []): string;

    /**
     * Generate a new Data Encryption Key (DEK) encrypted with the master key
     *
     * @param string $masterKeyId Master key identifier
     * @param int $keyLength Length of the DEK in bytes (default: 32 for AES-256)
     * @param array<string, mixed> $encryptionContext Additional context for encryption
     * @return DataEncryptionKey The generated DEK with both plaintext and encrypted versions
     * @throws KeyManagementException
     */
    public function generateDataEncryptionKey(
        string $masterKeyId,
        int $keyLength = 32,
        array $encryptionContext = []
    ): DataEncryptionKey;

    /**
     * Decrypt an encrypted Data Encryption Key
     *
     * @param string $encryptedDek The encrypted DEK blob
     * @param string $masterKeyId Master key identifier used to encrypt the DEK
     * @param array<string, mixed> $encryptionContext Additional context used during encryption
     * @return string The decrypted plaintext DEK
     * @throws KeyManagementException
     */
    public function decryptDataEncryptionKey(
        string $encryptedDek,
        string $masterKeyId,
        array $encryptionContext = []
    ): string;

    /**
     * Encrypt a plaintext Data Encryption Key
     *
     * @param string $plaintextDek The plaintext DEK to encrypt
     * @param string $masterKeyId Master key identifier
     * @param array<string, mixed> $encryptionContext Additional context for encryption
     * @return string The encrypted DEK blob
     * @throws KeyManagementException
     */
    public function encryptDataEncryptionKey(
        #[SensitiveParameter] string $plaintextDek,
        string $masterKeyId,
        array $encryptionContext = []
    ): string;

    /**
     * Rotate a Data Encryption Key by generating a new version
     *
     * @param string $keyId The DEK identifier to rotate
     * @param string $masterKeyId Master key identifier
     * @param array<string, mixed> $encryptionContext Additional context for encryption
     * @return DataEncryptionKey The new rotated DEK
     * @throws KeyManagementException
     */
    public function rotateDataEncryptionKey(
        string $keyId,
        string $masterKeyId,
        array $encryptionContext = []
    ): DataEncryptionKey;

    /**
     * Check if a master key exists and is accessible
     *
     * @param string $masterKeyId Master key identifier
     * @return bool True if the key exists and is accessible
     */
    public function masterKeyExists(string $masterKeyId): bool;

    /**
     * Get master key metadata
     *
     * @param string $masterKeyId Master key identifier
     * @return array<string, mixed> Key metadata
     * @throws KeyManagementException
     */
    public function getMasterKeyMetadata(string $masterKeyId): array;
}
