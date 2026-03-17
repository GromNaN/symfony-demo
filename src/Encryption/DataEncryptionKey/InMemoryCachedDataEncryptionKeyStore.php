<?php

namespace App\Encryption\DataEncryptionKey;

/**
 * InMemoryCachedDataEncryptionKeyStore caches DEKs in memory over an inner store.
 */
final class InMemoryCachedDataEncryptionKeyStore implements DataEncryptionKeyStore
{
    /** @var array<string, DataEncryptionKey> */
    private array $cache = [];

    public function __construct(private readonly DataEncryptionKeyStore $innerStore)
    {
    }

    public function getKey(string $id): DataEncryptionKey
    {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $key = $this->innerStore->getKey($id);
        $this->cache[$id] = $key;

        return $key;
    }

    public function clear(): void
    {
        $this->cache = [];
    }
}
