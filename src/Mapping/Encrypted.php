<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Mapping;

use Attribute;

/**
 * Attribute to mark fields for encryption with queryable encryption support.
 *
 * Inspired by MongoDB's queryable encryption, this attribute supports:
 * - Deterministic encryption for exact match queries
 * - Random encryption for high security
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Encrypted
{
    public const string ALGORITHM_DETERMINISTIC = 'AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic';
    public const string ALGORITHM_RANDOM = 'AEAD_AES_256_CBC_HMAC_SHA_512-Random';

    public function __construct(
        public readonly string $algorithm = self::ALGORITHM_RANDOM,
        public readonly ?string $keyId = null,
        public readonly ?string $keyAltName = null,
        public readonly bool $queryable = false,
    ) {
    }

    public function isDeterministic(): bool
    {
        return $this->algorithm === self::ALGORITHM_DETERMINISTIC;
    }

    public function isQueryable(): bool
    {
        return $this->queryable || $this->isDeterministic();
    }
}
