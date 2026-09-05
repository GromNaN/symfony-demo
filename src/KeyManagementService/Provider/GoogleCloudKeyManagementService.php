<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\KeyManagementService\Provider;

use Google\Cloud\Kms\V1\Client\KeyManagementServiceClient;
use Google\Cloud\Kms\V1\CreateCryptoKeyRequest;
use Google\Cloud\Kms\V1\CryptoKey;
use Google\Cloud\Kms\V1\DecryptRequest;
use Google\Cloud\Kms\V1\EncryptRequest;
use Google\Cloud\Kms\V1\GetCryptoKeyRequest;
use Google\Cloud\Kms\V1\CryptoKey\CryptoKeyPurpose;
use Gromnan\DoctrineEncrypt\KeyManagementService\KeyManagementServiceInterface;
use Gromnan\DoctrineEncrypt\KeyManagementService\DataEncryptionKey;
use Gromnan\DoctrineEncrypt\KeyManagementService\KeyManagementException;
use SensitiveParameter;

/**
 * Google Cloud KMS implementation of Key Management Service.
 * Requires google/cloud-kms package.
 */
final class GoogleCloudKeyManagementService implements KeyManagementServiceInterface
{
    private int $dekVersionCounter = 1;

    public function __construct(
        private readonly KeyManagementServiceClient $kmsClient,
        private readonly string $projectId,
        private readonly string $locationId = 'global',
        private readonly string $keyRingId = 'doctrine-encryption'
    ) {
    }

    public function createMasterKey(string $keyId, array $metadata = []): string
    {
        try {
            $keyRingName = $this->kmsClient->keyRingName($this->projectId, $this->locationId, $this->keyRingId);

            $cryptoKey = new CryptoKey([
                'purpose' => CryptoKeyPurpose::ENCRYPT_DECRYPT,
                'labels' => $this->buildLabels($keyId, $metadata),
            ]);

            $request = new CreateCryptoKeyRequest([
                'parent' => $keyRingName,
                'crypto_key_id' => $keyId,
                'crypto_key' => $cryptoKey,
            ]);

            $result = $this->kmsClient->createCryptoKey($request);

            return $result->getName();
        } catch (\Throwable $e) {
            throw KeyManagementException::keyCreationFailed($keyId, $e);
        }
    }

    public function generateDataEncryptionKey(
        string $masterKeyId,
        int $keyLength = 32,
        array $encryptionContext = []
    ): DataEncryptionKey {
        if ($keyLength < 1 || $keyLength > 1024) {
            throw KeyManagementException::invalidKeyLength($keyLength);
        }

        // Google Cloud KMS doesn't have a built-in generateDataKey like AWS
        // We need to generate the key locally and then encrypt it
        try {
            $plaintextDek = base64_encode(random_bytes($keyLength));
            $encryptedDek = $this->encryptDataEncryptionKey($plaintextDek, $masterKeyId, $encryptionContext);

            $dekId = $this->generateDekId($masterKeyId);

            return new DataEncryptionKey(
                keyId: $dekId,
                plaintextKey: $plaintextDek,
                encryptedKey: $encryptedDek,
                masterKeyId: $masterKeyId,
                encryptionContext: $encryptionContext,
                createdAt: new \DateTimeImmutable(),
                version: $this->dekVersionCounter++
            );
        } catch (\Throwable $e) {
            throw KeyManagementException::encryptionFailed($masterKeyId, $e);
        }
    }

    public function decryptDataEncryptionKey(
        string $encryptedDek,
        string $masterKeyId,
        array $encryptionContext = []
    ): string {
        try {
            $request = new DecryptRequest([
                'name' => $masterKeyId,
                'ciphertext' => base64_decode($encryptedDek),
                'additional_authenticated_data' => empty($encryptionContext) ? '' : json_encode($encryptionContext),
            ]);

            $result = $this->kmsClient->decrypt($request);

            return base64_encode($result->getPlaintext());
        } catch (\Throwable $e) {
            throw KeyManagementException::decryptionFailed($masterKeyId, $e);
        }
    }

    public function encryptDataEncryptionKey(
        #[SensitiveParameter] string $plaintextDek,
        string $masterKeyId,
        array $encryptionContext = []
    ): string {
        try {
            $request = new EncryptRequest([
                'name' => $masterKeyId,
                'plaintext' => base64_decode($plaintextDek),
                'additional_authenticated_data' => empty($encryptionContext) ? '' : json_encode($encryptionContext),
            ]);

            $result = $this->kmsClient->encrypt($request);

            return base64_encode($result->getCiphertext());
        } catch (\Throwable $e) {
            throw KeyManagementException::encryptionFailed($masterKeyId, $e);
        }
    }

    public function rotateDataEncryptionKey(
        string $keyId,
        string $masterKeyId,
        array $encryptionContext = []
    ): DataEncryptionKey {
        return $this->generateDataEncryptionKey($masterKeyId, 32, $encryptionContext);
    }

    public function masterKeyExists(string $masterKeyId): bool
    {
        try {
            $request = new GetCryptoKeyRequest(['name' => $masterKeyId]);
            $this->kmsClient->getCryptoKey($request);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getMasterKeyMetadata(string $masterKeyId): array
    {
        try {
            $request = new GetCryptoKeyRequest(['name' => $masterKeyId]);
            $cryptoKey = $this->kmsClient->getCryptoKey($request);

            $createdAt = $cryptoKey->getCreateTime();

            return [
                'keyId' => $this->extractKeyId($masterKeyId),
                'name' => $cryptoKey->getName(),
                'purpose' => $cryptoKey->getPurpose(),
                'labels' => iterator_to_array($cryptoKey->getLabels()),
                'createdAt' => $createdAt ? $createdAt->toDateTime()->format(\DateTimeInterface::ATOM) : null,
                'provider' => 'google-cloud-kms',
                'projectId' => $this->projectId,
                'locationId' => $this->locationId,
                'keyRingId' => $this->keyRingId,
            ];
        } catch (\Throwable $e) {
            throw KeyManagementException::keyNotFound($masterKeyId);
        }
    }

    /**
     * Create a key ring if it doesn't exist
     */
    public function ensureKeyRing(): void
    {
        try {
            $locationName = $this->kmsClient->locationName($this->projectId, $this->locationId);
            $keyRingName = $this->kmsClient->keyRingName($this->projectId, $this->locationId, $this->keyRingId);

            // Try to get the key ring first
            try {
                $this->kmsClient->getKeyRing(['name' => $keyRingName]);
                return; // Key ring already exists
            } catch (\Throwable) {
                // Key ring doesn't exist, create it
            }

            $this->kmsClient->createKeyRing([
                'parent' => $locationName,
                'key_ring_id' => $this->keyRingId,
            ]);
        } catch (\Throwable $e) {
            throw new KeyManagementException("Failed to ensure key ring exists: {$this->keyRingId}", 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, string>
     */
    private function buildLabels(string $keyId, array $metadata): array
    {
        $labels = [
            'name' => $this->sanitizeLabelValue($keyId),
            'purpose' => 'doctrine-encryption',
        ];

        foreach ($metadata as $key => $value) {
            if (is_string($value)) {
                $sanitizedKey = $this->sanitizeLabelKey($key);
                $sanitizedValue = $this->sanitizeLabelValue($value);
                $labels[$sanitizedKey] = $sanitizedValue;
            }
        }

        return $labels;
    }

    private function sanitizeLabelKey(string $key): string
    {
        // Google Cloud labels must match [a-z0-9_-]{1,63}
        $sanitized = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $key));
        return substr($sanitized, 0, 63);
    }

    private function sanitizeLabelValue(string $value): string
    {
        // Google Cloud label values must match [a-z0-9_-]{1,63}
        $sanitized = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $value));
        return substr($sanitized, 0, 63);
    }

    private function extractKeyId(string $keyName): string
    {
        // Extract key ID from full key name (projects/*/locations/*/keyRings/*/cryptoKeys/*)
        $parts = explode('/', $keyName);
        return end($parts);
    }

    private function generateDekId(string $masterKeyId): string
    {
        $keyIdHash = hash('sha256', $masterKeyId);
        return 'gcp_dek_' . substr($keyIdHash, 0, 8) . '_' . bin2hex(random_bytes(8)) . '_' . time();
    }
}
