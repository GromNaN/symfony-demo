<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Tests\Unit;

use Gromnan\DoctrineEncrypt\Mapping\Encrypted;
use PHPUnit\Framework\TestCase;

class EncryptedAttributeTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $encrypted = new Encrypted();

        $this->assertSame(Encrypted::ALGORITHM_RANDOM, $encrypted->algorithm);
        $this->assertNull($encrypted->keyId);
        $this->assertNull($encrypted->keyAltName);
        $this->assertFalse($encrypted->queryable);
        $this->assertFalse($encrypted->isDeterministic());
        $this->assertFalse($encrypted->isQueryable());
    }

    public function testDeterministicConfiguration(): void
    {
        $encrypted = new Encrypted(
            algorithm: Encrypted::ALGORITHM_DETERMINISTIC,
            keyAltName: 'user-key',
            queryable: true
        );

        $this->assertSame(Encrypted::ALGORITHM_DETERMINISTIC, $encrypted->algorithm);
        $this->assertSame('user-key', $encrypted->keyAltName);
        $this->assertTrue($encrypted->queryable);
        $this->assertTrue($encrypted->isDeterministic());
        $this->assertTrue($encrypted->isQueryable());
    }

    public function testRandomWithQueryableConfiguration(): void
    {
        $encrypted = new Encrypted(
            algorithm: Encrypted::ALGORITHM_RANDOM,
            keyId: 'payment-key-id',
            queryable: true
        );

        $this->assertSame(Encrypted::ALGORITHM_RANDOM, $encrypted->algorithm);
        $this->assertSame('payment-key-id', $encrypted->keyId);
        $this->assertTrue($encrypted->queryable);
        $this->assertFalse($encrypted->isDeterministic());
        $this->assertTrue($encrypted->isQueryable());
    }

    public function testDeterministicIsAlwaysQueryable(): void
    {
        // Deterministic encryption should always be queryable regardless of queryable flag
        $encrypted = new Encrypted(
            algorithm: Encrypted::ALGORITHM_DETERMINISTIC,
            queryable: false
        );

        $this->assertTrue($encrypted->isDeterministic());
        $this->assertTrue($encrypted->isQueryable());
    }
}
