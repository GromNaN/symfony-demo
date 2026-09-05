<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\KeyManagementService\Provider;

use AsyncAws\Kms\KmsClient;
use AsyncAws\Kms\ValueObject\KeySpec;
use AsyncAws\Kms\ValueObject\KeyUsageType;
use AsyncAws\Kms\Input\CreateKeyRequest;
use AsyncAws\Kms\Input\GenerateDataKeyRequest;
use AsyncAws\Kms\Input\DecryptRequest;
use AsyncAws\Kms\Input\EncryptRequest;
use AsyncAws\Kms\Input\DescribeKeyRequest;
use Gromnan\DoctrineEncrypt\KeyManagementService\KeyManagementServiceInterface;
use Gromnan\DoctrineEncrypt\KeyManagementService\DataEncryptionKey;
use Gromnan\DoctrineEncrypt\KeyManagementService\KeyManagementException;
use SensitiveParameter;

/**
 * AWS KMS implementation of Key Management Service.
 * Requires async-aws/kms package.
 */
final class AwsKeyManagementService implements KeyManagementServiceInterface
{
    private int $dekVersionCounter = 1;

    public function __construct(
        private readonly KmsClient $kmsClient
    ) {
    }

    public function createMasterKey(string $keyId, array $metadata = []): string
    {
        try {
            $input = new CreateKeyRequest([
                'Description' => $metadata['description'] ?? "Master key for {$keyId}",
                'KeyUsage' => KeyUsageType::ENCRYPT_DECRYPT,
                'KeySpec' => KeySpec::SYMMETRIC_DEFAULT,
                'Tags' => $this->buildTags($keyId, $metadata),
            ]);

            $result = $this->kmsClient->createKey($input);
            $keyMetadata = $result->getKeyMetadata();

            if ($keyMetadata === null || $keyMetadata->getKeyId() === null) {
                throw KeyManagementException::keyCreationFailed($keyId);
            }

            return $keyMetadata->getArn() ?? $keyMetadata->getKeyId();
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

        try {
            $input = new GenerateDataKeyRequest([
                'KeyId' => $masterKeyId,
                'KeySpec' => $this->getKeySpecForLength($keyLength),
                'EncryptionContext' => $encryptionContext,
            ]);

            $result = $this->kmsClient->generateDataKey($input);

            $plaintextKey = $result->getPlaintext();
            $encryptedKey = $result->getCiphertextBlob();

            if ($plaintextKey === null || $encryptedKey === null) {
                throw new KeyManagementException('Failed to generate data encryption key');
            }

            $dekId = $this->generateDekId($masterKeyId);

            return new DataEncryptionKey(
                keyId: $dekId,
                plaintextKey: base64_encode($plaintextKey),
                encryptedKey: base64_encode($encryptedKey),
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
            $input = new DecryptRequest([
                'CiphertextBlob' => base64_decode($encryptedDek),
                'EncryptionContext' => $encryptionContext,
                'KeyId' => $masterKeyId,
            ]);

            $result = $this->kmsClient->decrypt($input);
            $plaintext = $result->getPlaintext();

            if ($plaintext === null) {
                throw KeyManagementException::decryptionFailed($masterKeyId);
            }

            return base64_encode($plaintext);
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
            $input = new EncryptRequest([
                'KeyId' => $masterKeyId,
                'Plaintext' => base64_decode($plaintextDek),
                'EncryptionContext' => $encryptionContext,
            ]);

            $result = $this->kmsClient->encrypt($input);
            $ciphertextBlob = $result->getCiphertextBlob();

            if ($ciphertextBlob === null) {
                throw KeyManagementException::encryptionFailed($masterKeyId);
            }

            return base64_encode($ciphertextBlob);
        } catch (\Throwable $e) {
            throw KeyManagementException::encryptionFailed($masterKeyId, $e);
        }
    }

    public function rotateDataEncryptionKey(
        string $keyId,
        string $masterKeyId,
        array $encryptionContext = []
    ): DataEncryptionKey {
        // AWS KMS handles key rotation at the master key level
        // For DEK rotation, we generate a new DEK
        return $this->generateDataEncryptionKey($masterKeyId, 32, $encryptionContext);
    }

    public function masterKeyExists(string $masterKeyId): bool
    {
        try {
            $input = new DescribeKeyRequest(['KeyId' => $masterKeyId]);
            $result = $this->kmsClient->describeKey($input);

            return $result->getKeyMetadata() !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getMasterKeyMetadata(string $masterKeyId): array
    {
        try {
            $input = new DescribeKeyRequest(['KeyId' => $masterKeyId]);
            $result = $this->kmsClient->describeKey($input);
            $metadata = $result->getKeyMetadata();

            if ($metadata === null) {
                throw KeyManagementException::keyNotFound($masterKeyId);
            }

            return [
                'keyId' => $metadata->getKeyId(),
                'arn' => $metadata->getArn(),
                'description' => $metadata->getDescription(),
                'keyUsage' => $metadata->getKeyUsage()?->value,
                'keyState' => $metadata->getKeyState()?->value,
                'createdAt' => $metadata->getCreationDate()?->format(\DateTimeInterface::ATOM),
                'provider' => 'aws-kms',
                'region' => $this->kmsClient->getConfiguration()['region'] ?? 'unknown',
            ];
        } catch (\Throwable $e) {
            throw KeyManagementException::keyNotFound($masterKeyId);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<int, array{TagKey: string, TagValue: string}>
     */
    private function buildTags(string $keyId, array $metadata): array
    {
        $tags = [
            ['TagKey' => 'Name', 'TagValue' => $keyId],
            ['TagKey' => 'Purpose', 'TagValue' => 'DoctrineEncryption'],
        ];

        foreach ($metadata as $key => $value) {
            if (is_string($value) && $key !== 'description') {
                $tags[] = ['TagKey' => $key, 'TagValue' => $value];
            }
        }

        return $tags;
    }

    private function getKeySpecForLength(int $keyLength): string
    {
        return match ($keyLength) {
            32 => 'AES_256',
            16 => 'AES_128',
            default => 'AES_256', // Default to AES-256
        };
    }

    private function generateDekId(string $masterKeyId): string
    {
        $keyIdHash = hash('sha256', $masterKeyId);
        return 'aws_dek_' . substr($keyIdHash, 0, 8) . '_' . bin2hex(random_bytes(8)) . '_' . time();
    }
}
