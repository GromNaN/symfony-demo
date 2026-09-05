<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Tests\Unit;

use Gromnan\DoctrineEncrypt\Encryption\StaticKeyProvider;
use PHPUnit\Framework\TestCase;

class StaticKeyProviderTest extends TestCase
{
    public function testDefaultKeys(): void
    {
        $provider = new StaticKeyProvider();

        // Should have default keys
        $defaultKey = $provider->getDefaultKey();
        $this->assertIsString($defaultKey);
        $this->assertNotEmpty($defaultKey);

        // Should be able to get keys by name
        $userKey = $provider->getKey(keyAltName: 'user-key');
        $this->assertIsString($userKey);
        $this->assertNotSame($defaultKey, $userKey);
    }

    public function testCustomKeys(): void
    {
        $customKeys = [
            'key1' => base64_encode('custom-key-1-32-bytes-long-test'),
            'key2' => base64_encode('custom-key-2-32-bytes-long-test'),
        ];

        $provider = new StaticKeyProvider($customKeys, 'key2');

        $this->assertSame($customKeys['key1'], $provider->getKey('key1'));
        $this->assertSame($customKeys['key2'], $provider->getKey('key2'));
        $this->assertSame($customKeys['key2'], $provider->getDefaultKey());
    }

    public function testAddKey(): void
    {
        $provider = new StaticKeyProvider();
        $newKey = base64_encode('new-key-32-bytes-long-for-test');

        $provider->addKey('new-key', $newKey);

        $this->assertSame($newKey, $provider->getKey('new-key'));
    }

    public function testGetNonExistentKeyThrowsException(): void
    {
        $provider = new StaticKeyProvider();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Key not found: non-existent');

        $provider->getKey('non-existent');
    }

    public function testKeyIdTakesPrecedenceOverAltName(): void
    {
        $provider = new StaticKeyProvider([
            'id-key' => base64_encode('key-by-id-32-bytes-long-test-key'),
            'alt-key' => base64_encode('key-by-alt-32-bytes-long-test-key'),
        ]);

        $key = $provider->getKey('id-key', 'alt-key');
        $this->assertSame(base64_encode('key-by-id-32-bytes-long-test-key'), $key);
    }
}
