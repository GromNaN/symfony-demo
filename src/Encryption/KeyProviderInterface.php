<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Encryption;

interface KeyProviderInterface
{
    /**
     * Get encryption key by ID or alternative name
     */
    public function getKey(?string $keyId = null, ?string $keyAltName = null): string;

    /**
     * Get the default data encryption key
     */
    public function getDefaultKey(): string;
}
