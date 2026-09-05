<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\KeyManagementService;

use SensitiveParameter;

/**
 * Local Key Management Service implementation for development and testing.
 * Stores master keys in memory and uses local encryption.
 *
 * WARNING: This implementation is NOT suitable for production use.
 * Use cloud-based KMS implementations for production environments.
 */
final class LocalKeyManagementService implements KeyManagementServiceInterface
{
    private const CIPHER_AES_256_GCM = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    /** @var array<string, array{key: string, metadata: array<string, mixed>, created_at: \DateTimeImmutable}> */
    private array $masterKeys = [];

    /** @var array<string, int> */
    private array $dekVersions = [];

    public function __construct(
        #[SensitiveParameter] private readonly ?string $defaultMasterKey = null
    ) {
        if ($this->defaultMasterKey !== null) {
            $this->masterKeys['default'] = [
                'key' => $this->defaultMasterKey,
                'metadata' => ['description' => 'Default master key'],
                'created_at' => new \DateTimeImmutable(),
            ];
        }
    }

    public function createMasterKey(string $keyId, array $metadata = []): string
    {
        if (isset($this->masterKeys[$keyId])) {
            throw new KeyManagementException("Master key already exists: {$keyId}");
        }

        $masterKey = base64_encode(random_bytes(32)); // 256-bit master key

        $this->masterKeys[$keyId] = [
            'key' => $masterKey,
            'metadata' => array_merge(['description' => "Master key {$keyId}"], $metadata),
            'created_at' => new \DateTimeImmutable(),
        ];

        return "local://{$keyId}";
    }

    public function generateDataEncryptionKey(
        string $masterKeyId,
        int $keyLength = 32,
        array $encryptionContext = []
    ): DataEncryptionKey {
        if ($keyLength < 1 || $keyLength > 256) {
            throw KeyManagementException::invalidKeyLength($keyLength);
        }

        if (!$this->masterKeyExists($masterKeyId)) {
            throw KeyManagementException::keyNotFound($masterKeyId);
        }

        // Generate a unique DEK ID
        $dekId = $this->generateDekId($masterKeyId);

        // Generate random DEK
        $plaintextDek = base64_encode(random_bytes($keyLength));

        // Encrypt DEK with master key
        $encryptedDek = $this->encryptDataEncryptionKey($plaintextDek, $masterKeyId, $encryptionContext);

        // Get version for this DEK
        $version = $this->getNextDekVersion($dekId);

        return new DataEncryptionKey(
            keyId: $dekId,
            plaintextKey: $plaintextDek,
            encryptedKey: $encryptedDek,
            masterKeyId: $masterKeyId,
            encryptionContext: $encryptionContext,
            createdAt: new \DateTimeImmutable(),
            version: $version
        );
    }

    public function decryptDataEncryptionKey(
        string $encryptedDek,
        string $masterKeyId,
        array $encryptionContext = []
    ): string {
        if (!$this->masterKeyExists($masterKeyId)) {
            throw KeyManagementException::keyNotFound($masterKeyId);
        }

        try {
            $masterKey = base64_decode($this->masterKeys[$masterKeyId]['key']);

            // Decode the encrypted DEK
            $decoded = base64_decode($encryptedDek);
            if ($decoded === false || strlen($decoded) < self::IV_LENGTH + self::TAG_LENGTH + 1) {
                throw new \InvalidArgumentException('Invalid encrypted DEK format');
            }

            $iv = substr($decoded, 0, self::IV_LENGTH);
            $tag = substr($decoded, self::IV_LENGTH, self::TAG_LENGTH);
            $encrypted = substr($decoded, self::IV_LENGTH + self::TAG_LENGTH);

            // Include encryption context in AAD if provided
            $aad = empty($encryptionContext) ? '' : json_encode($encryptionContext);

            $decrypted = openssl_decrypt($encrypted, self::CIPHER_AES_256_GCM, $masterKey, OPENSSL_RAW_DATA, $iv, $tag, $aad);

            if ($decrypted === false) {
                throw KeyManagementException::decryptionFailed($masterKeyId);
            }

            return $decrypted;
        } catch (\Throwable $e) {
            throw KeyManagementException::decryptionFailed($masterKeyId, $e);
        }
    }

    public function encryptDataEncryptionKey(
        #[SensitiveParameter] string $plaintextDek,
        string $masterKeyId,
        array $encryptionContext = []
    ): string {
        if (!$this->masterKeyExists($masterKeyId)) {
            throw KeyManagementException::keyNotFound($masterKeyId);
        }

        try {
            $masterKey = base64_decode($this->masterKeys[$masterKeyId]['key']);
            $iv = random_bytes(self::IV_LENGTH);

            // Include encryption context in AAD if provided
            $aad = empty($encryptionContext) ? '' : json_encode($encryptionContext);

            $encrypted = openssl_encrypt($plaintextDek, self::CIPHER_AES_256_GCM, $masterKey, OPENSSL_RAW_DATA, $iv, $tag, $aad);

            if ($encrypted === false) {
                throw KeyManagementException::encryptionFailed($masterKeyId);
            }

            return base64_encode($iv . $tag . $encrypted);
        } catch (\Throwable $e) {
            throw KeyManagementException::encryptionFailed($masterKeyId, $e);
        }
    }

    public function rotateDataEncryptionKey(
        string $keyId,
        string $masterKeyId,
        array $encryptionContext = []
    ): DataEncryptionKey {
        // For rotation, we generate a completely new DEK
        return $this->generateDataEncryptionKey($masterKeyId, 32, $encryptionContext);
    }

    public function masterKeyExists(string $masterKeyId): bool
    {
        return isset($this->masterKeys[$masterKeyId]);
    }

    public function getMasterKeyMetadata(string $masterKeyId): array
    {
        if (!$this->masterKeyExists($masterKeyId)) {
            throw KeyManagementException::keyNotFound($masterKeyId);
        }

        $keyData = $this->masterKeys[$masterKeyId];
        return [
            'keyId' => $masterKeyId,
            'metadata' => $keyData['metadata'],
            'createdAt' => $keyData['created_at']->format(\DateTimeInterface::ATOM),
            'provider' => 'local',
            'keyUri' => "local://{$masterKeyId}",
        ];
    }

    /**
     * Add a pre-existing master key (useful for testing)
     */
    public function addMasterKey(string $keyId, #[SensitiveParameter] string $key, array $metadata = []): void
    {
        $this->masterKeys[$keyId] = [
            'key' => $key,
            'metadata' => $metadata,
            'created_at' => new \DateTimeImmutable(),
        ];
    }

    /**
     * Get all master key IDs (for testing/debugging)
     *
     * @return string[]
     */
    public function listMasterKeys(): array
    {
        return array_keys($this->masterKeys);
    }

    private function generateDekId(string $masterKeyId): string
    {
        return 'dek_' . $masterKeyId . '_' . bin2hex(random_bytes(8)) . '_' . time();
    }

    private function getNextDekVersion(string $dekId): int
    {
        if (!isset($this->dekVersions[$dekId])) {
            $this->dekVersions[$dekId] = 0;
        }

        return ++$this->dekVersions[$dekId];
    }
}
