<?php

declare(strict_types=1);

namespace App\Tests\Encryption\DataEncryptionKey;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\DataEncryptionKey\DataEncryptionKeyStore;
use App\Encryption\DataEncryptionKey\InMemoryCachedDataEncryptionKeyStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryCachedDataEncryptionKeyStore::class)]
final class InMemoryCachedDataEncryptionKeyStoreTest extends TestCase
{
    public function testGetKeyUsesCacheAfterFirstLookup(): void
    {
        $innerStore = new class implements DataEncryptionKeyStore {
            public int $calls = 0;

            public function getKey(string $id): DataEncryptionKey
            {
                ++$this->calls;

                return new DataEncryptionKey('master', 'encrypted-' . $id);
            }
        };

        $store = new InMemoryCachedDataEncryptionKeyStore($innerStore);

        $first = $store->getKey('user-key');
        $second = $store->getKey('user-key');

        self::assertSame(1, $innerStore->calls);
        self::assertSame($first, $second);
    }

    public function testClearFlushesCache(): void
    {
        $innerStore = new class implements DataEncryptionKeyStore {
            public int $calls = 0;

            public function getKey(string $id): DataEncryptionKey
            {
                ++$this->calls;

                return new DataEncryptionKey('master', 'encrypted-' . $id);
            }
        };

        $store = new InMemoryCachedDataEncryptionKeyStore($innerStore);

        $store->getKey('user-key');
        $store->clear();
        $store->getKey('user-key');

        self::assertSame(2, $innerStore->calls);
    }
}

