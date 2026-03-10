<?php

declare(strict_types=1);

namespace App\Tests\Encryption\DataEncryptionKey;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\DataEncryptionKey\FileDataEncryptionKeyStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileDataEncryptionKeyStore::class)]
final class FileDataEncryptionKeyStoreTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/dek_store_' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $files = glob($this->tmpDir . '/*') ?: [];

            foreach ($files as $file) {
                @unlink($file);
            }

            @rmdir($this->tmpDir);
        }
    }

    public function testGetKeyReturnsDataEncryptionKeyFromJsonFile(): void
    {
        $id = 'key-1';
        file_put_contents(
            $this->tmpDir . '/' . $id,
            json_encode([
                'masterKeyId' => 'master-1',
                'encryptedKey' => 'enc-abc',
            ], JSON_THROW_ON_ERROR)
        );

        $store = $this->newFileStore($this->tmpDir);

        $key = $store->getKey($id);

        self::assertInstanceOf(DataEncryptionKey::class, $key);
        self::assertSame('master-1', $key->masterKeyId);
    }

    public function testGetKeyThrowsWhenFileDoesNotExist(): void
    {
        $store = $this->newFileStore($this->tmpDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $store->getKey('missing-key');
    }

    private function newFileStore(string $dirname): FileDataEncryptionKeyStore
    {
        $reflection = new \ReflectionClass(FileDataEncryptionKeyStore::class);
        $store = $reflection->newInstanceWithoutConstructor();

        $dirnameProperty = $reflection->getProperty('dirname');
        $dirnameProperty->setValue($store, $dirname);

        return $store;
    }
}

