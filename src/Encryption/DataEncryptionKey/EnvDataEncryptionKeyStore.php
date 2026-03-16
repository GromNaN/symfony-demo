<?php

declare(strict_types=1);

namespace App\Encryption\DataEncryptionKey;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EnvDataEncryptionKeyStore implements DataEncryptionKeyStore
{
    public function __construct(
        #[Autowire(env: 'DATA_ENCRYPTION_KEY')]
        private readonly string $dek,
    ) {
    }

    public function getKey(string $id): DataEncryptionKey
    {
        return new DataEncryptionKey($id, null, null, $this->dek);
    }
}

