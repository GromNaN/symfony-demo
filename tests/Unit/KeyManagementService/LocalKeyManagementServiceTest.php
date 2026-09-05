<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Tests\Unit\KeyManagementService;

use Gromnan\DoctrineEncrypt\KeyManagementService\LocalKeyManagementService;
use Gromnan\DoctrineEncrypt\KeyManagementService\KeyManagementException;
use PHPUnit\Framework\TestCase;

class LocalKeyManagementServiceTest extends TestCase
{
    private LocalKeyManagementService $kms;

    protected function setUp(): void
    {
        $this->kms = new LocalKeyManagementService();
    }

    public function testCreateMasterKey(): void
    {
        $keyId = 'test-master-key';
        $metadata = ['description' => 'Test master key'];

        $keyUri = $this->kms->createMasterKey($keyId, $metadata);

        $this->assertSame("local://{$keyId}", $keyUri);
        $this->assertTrue($this->kms->masterKeyExists($keyId));
    }

    public function testCreateDuplicateMasterKeyThrowsException(): void
    {
        $keyId = 'duplicate-key';

        $this->kms->createMasterKey($keyId);

        $this->expectException(KeyManagementException::class);
        $this->expectExceptionMessage("Master key already exists: {$keyId}");

        $this->kms->createMasterKey($keyId);
    }

    public function testGenerateDataEncryptionKey(): void
    {
        $masterKeyId = 'master-key';
        $this->kms->createMasterKey($masterKeyId);

        $dek = $this->kms->generateDataEncryptionKey($masterKeyId, 32, ['context' => 'test']);

        $this->assertNotEmpty($dek->keyId);
        $this->assertNotEmpty($dek->plaintextKey);
        $this->assertNotEmpty($dek->encryptedKey);
        $this->assertSame($masterKeyId, $dek->masterKeyId);
        $this->assertSame(['context' => 'test'], $dek->encryptionContext);
        $this->assertSame(32, $dek->getKeyLength());
        $this->assertInstanceOf(\DateTimeImmutable::class, $dek->createdAt);
        $this->assertSame(1, $dek->version);
    }

    public function testGenerateDataEncryptionKeyWithInvalidLength(): void
    {
        $masterKeyId = 'master-key';
        $this->kms->createMasterKey($masterKeyId);

        $this->expectException(KeyManagementException::class);
        $this->expectExceptionMessage('Invalid key length: 300 bytes');

        $this->kms->generateDataEncryptionKey($masterKeyId, 300);
    }

    public function testGenerateDataEncryptionKeyWithNonExistentMasterKey(): void
    {
        $this->expectException(KeyManagementException::class);
        $this->expectExceptionMessage('Key not found: non-existent');

        $this->kms->generateDataEncryptionKey('non-existent');
    }

    public function testEncryptAndDecryptDataEncryptionKey(): void
    {
        $masterKeyId = 'master-key';
        $this->kms->createMasterKey($masterKeyId);

        $plaintextDek = base64_encode('test-dek-32-bytes-long-for-aes256');
        $encryptionContext = ['user' => 'test-user'];

        // Encrypt
        $encryptedDek = $this->kms->encryptDataEncryptionKey($plaintextDek, $masterKeyId, $encryptionContext);
        $this->assertNotEmpty($encryptedDek);
        $this->assertNotSame($plaintextDek, $encryptedDek);

        // Decrypt
        $decryptedDek = $this->kms->decryptDataEncryptionKey($encryptedDek, $masterKeyId, $encryptionContext);
        $this->assertSame($plaintextDek, $decryptedDek);
    }

    public function testDecryptDataEncryptionKeyWithWrongContext(): void
    {
        $masterKeyId = 'master-key';
        $this->kms->createMasterKey($masterKeyId);

        $plaintextDek = base64_encode('test-dek-32-bytes-long-for-aes256');
        $encryptedDek = $this->kms->encryptDataEncryptionKey($plaintextDek, $masterKeyId, ['user' => 'test-user']);

        $this->expectException(KeyManagementException::class);
        $this->expectExceptionMessage('Failed to decrypt with key');

        // Try to decrypt with different context
        $this->kms->decryptDataEncryptionKey($encryptedDek, $masterKeyId, ['user' => 'different-user']);
    }

    public function testRotateDataEncryptionKey(): void
    {
        $masterKeyId = 'master-key';
        $this->kms->createMasterKey($masterKeyId);

        $originalDek = $this->kms->generateDataEncryptionKey($masterKeyId);
        $rotatedDek = $this->kms->rotateDataEncryptionKey($originalDek->keyId, $masterKeyId);

        $this->assertNotSame($originalDek->keyId, $rotatedDek->keyId);
        $this->assertNotSame($originalDek->plaintextKey, $rotatedDek->plaintextKey);
        $this->assertNotSame($originalDek->encryptedKey, $rotatedDek->encryptedKey);
        $this->assertSame($masterKeyId, $rotatedDek->masterKeyId);
    }

    public function testGetMasterKeyMetadata(): void
    {
        $keyId = 'test-key';
        $metadata = ['description' => 'Test key', 'owner' => 'test-user'];

        $this->kms->createMasterKey($keyId, $metadata);

        $keyMetadata = $this->kms->getMasterKeyMetadata($keyId);

        $this->assertSame($keyId, $keyMetadata['keyId']);
        $this->assertSame('Test key', $keyMetadata['metadata']['description']);
        $this->assertSame('test-user', $keyMetadata['metadata']['owner']);
        $this->assertSame('local', $keyMetadata['provider']);
        $this->assertSame("local://{$keyId}", $keyMetadata['keyUri']);
        $this->assertArrayHasKey('createdAt', $keyMetadata);
    }

    public function testGetMasterKeyMetadataForNonExistentKey(): void
    {
        $this->expectException(KeyManagementException::class);
        $this->expectExceptionMessage('Key not found: non-existent');

        $this->kms->getMasterKeyMetadata('non-existent');
    }

    public function testAddMasterKey(): void
    {
        $keyId = 'external-key';
        $key = base64_encode('external-key-32-bytes-long-test');
        $metadata = ['source' => 'external'];

        $this->kms->addMasterKey($keyId, $key, $metadata);

        $this->assertTrue($this->kms->masterKeyExists($keyId));

        $keyMetadata = $this->kms->getMasterKeyMetadata($keyId);
        $this->assertSame('external', $keyMetadata['metadata']['source']);
    }

    public function testListMasterKeys(): void
    {
        $this->kms->createMasterKey('key1');
        $this->kms->createMasterKey('key2');
        $this->kms->addMasterKey('key3', base64_encode('test-key-32-bytes-long-for-test'));

        $keys = $this->kms->listMasterKeys();

        $this->assertCount(3, $keys);
        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        $this->assertContains('key3', $keys);
    }

    public function testDataEncryptionKeyWithoutPlaintextKey(): void
    {
        $masterKeyId = 'master-key';
        $this->kms->createMasterKey($masterKeyId);

        $dek = $this->kms->generateDataEncryptionKey($masterKeyId);
        $safeDek = $dek->withoutPlaintextKey();

        $this->assertNotSame($dek->plaintextKey, $safeDek->plaintextKey);
        $this->assertEmpty($safeDek->plaintextKey);
        $this->assertSame($dek->encryptedKey, $safeDek->encryptedKey);
        $this->assertSame($dek->keyId, $safeDek->keyId);
        $this->assertSame($dek->masterKeyId, $safeDek->masterKeyId);
    }

    public function testDataEncryptionKeyIsEncryptedWith(): void
    {
        $masterKeyId = 'master-key';
        $this->kms->createMasterKey($masterKeyId);

        $dek = $this->kms->generateDataEncryptionKey($masterKeyId);

        $this->assertTrue($dek->isEncryptedWith($masterKeyId));
        $this->assertFalse($dek->isEncryptedWith('different-master-key'));
    }
}
