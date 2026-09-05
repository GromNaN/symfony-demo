<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Tests\Integration;

use Gromnan\DoctrineEncrypt\KeyManagementService\LocalKeyManagementService;
use Gromnan\DoctrineEncrypt\KeyManagementService\KeyManagementServiceFactory;
use Gromnan\DoctrineEncrypt\Tests\BaseTestCase;
use Gromnan\DoctrineEncrypt\Tests\Fixtures\User;

class KeyManagementServiceIntegrationTest extends BaseTestCase
{
    private LocalKeyManagementService $kms;

    protected function setUp(): void
    {
        // Don't call parent::setUp() as we want to set up KMS first

        // Create KMS and master keys
        $this->kms = KeyManagementServiceFactory::createLocal();
        $this->kms->createMasterKey('user-key', ['purpose' => 'User PII encryption']);
        $this->kms->createMasterKey('payment-key', ['purpose' => 'Payment data encryption']);

        // Create a DEK-aware key provider
        $this->keyProvider = new KmsKeyProvider($this->kms);

        // Now set up the rest of the test infrastructure
        parent::setUp();
    }

    public function testEntityEncryptionWithKmsGeneratedKeys(): void
    {
        // Create a user with KMS-generated keys
        $user = new User(
            name: 'Alice Smith',
            email: 'alice.smith@company.com',
            ssn: '123-45-6789'
        );
        $user->bloodType = 'A+';
        $user->medicalRecords = ['condition' => 'hypertension'];

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $userId = $user->id;
        $this->entityManager->clear();

        // Verify the user can be retrieved and decrypted
        $retrievedUser = $this->entityManager->find(User::class, $userId);

        $this->assertSame('Alice Smith', $retrievedUser->name);
        $this->assertSame('alice.smith@company.com', $retrievedUser->email);
        $this->assertSame('123-45-6789', $retrievedUser->ssn);
        $this->assertSame('A+', $retrievedUser->bloodType);
        $this->assertSame(['condition' => 'hypertension'], $retrievedUser->medicalRecords);
    }

    public function testKeyRotation(): void
    {
        // Create user with initial keys
        $user = new User('Bob Wilson', 'bob@example.com', '987-65-4321');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $originalEncryptedEmail = $this->getEncryptedFieldValue('users', 'email', $user->id);

        // Simulate key rotation by generating new DEKs
        $newDek = $this->kms->rotateDataEncryptionKey('old-dek-id', 'user-key');

        // Update the key provider with the new DEK
        $this->keyProvider->rotateKey('user-key', $newDek->plaintextKey);

        // Re-encrypt the user's data (in practice, this would be done in a migration)
        $this->entityManager->clear();
        $retrievedUser = $this->entityManager->find(User::class, $user->id);
        $retrievedUser->email = $retrievedUser->email; // Trigger re-encryption
        $this->entityManager->flush();

        $newEncryptedEmail = $this->getEncryptedFieldValue('users', 'email', $user->id);

        // The encrypted values should be different due to key rotation
        $this->assertNotSame($originalEncryptedEmail, $newEncryptedEmail);

        // But decryption should still work
        $this->entityManager->clear();
        $finalUser = $this->entityManager->find(User::class, $user->id);
        $this->assertSame('bob@example.com', $finalUser->email);
    }

    private function getEncryptedFieldValue(string $table, string $field, int $id): string
    {
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare("SELECT {$field} FROM {$table} WHERE id = ?");
        $result = $stmt->executeQuery([$id]);
        return $result->fetchOne();
    }
}

/**
 * Example KMS-aware key provider that integrates with our KeyManagementService
 */
class KmsKeyProvider implements \Gromnan\DoctrineEncrypt\Encryption\KeyProviderInterface
{
    private array $dekCache = [];

    public function __construct(
        private readonly LocalKeyManagementService $kms
    ) {
        // Pre-generate DEKs for our test keys
        $this->dekCache['user-key'] = $this->kms->generateDataEncryptionKey('user-key');
        $this->dekCache['payment-key'] = $this->kms->generateDataEncryptionKey('payment-key');
        $this->dekCache['default'] = $this->kms->generateDataEncryptionKey('user-key'); // Default uses user-key master
    }

    public function getKey(?string $keyId = null, ?string $keyAltName = null): string
    {
        $key = $keyId ?? $keyAltName ?? 'default';

        if (!isset($this->dekCache[$key])) {
            // In practice, you'd load the encrypted DEK from storage and decrypt it
            throw new \InvalidArgumentException("DEK not found: {$key}");
        }

        return $this->dekCache[$key]->plaintextKey;
    }

    public function getDefaultKey(): string
    {
        return $this->getKey('default');
    }

    public function rotateKey(string $keyAltName, string $newDekKey): void
    {
        // In practice, you'd store both old and new keys during rotation period
        $this->dekCache[$keyAltName] = new \Gromnan\DoctrineEncrypt\KeyManagementService\DataEncryptionKey(
            keyId: 'rotated-' . $keyAltName,
            plaintextKey: $newDekKey,
            encryptedKey: '', // Would be properly encrypted in practice
            masterKeyId: 'user-key',
            createdAt: new \DateTimeImmutable()
        );
    }
}
