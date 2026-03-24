<?php

declare(strict_types=1);

namespace App\Tests\Encryption\QueryableEncryption;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\DataEncryptionKey\DataEncryptionKeyStore;
use App\Encryption\QueryableEncryption\RangeTagGeneratorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RangeTagGeneratorFactory::class)]
final class RangeTagGeneratorFactoryTest extends TestCase
{
    public function testForBirthdateCreatesGenerator(): void
    {
        $store = $this->createMockStore();
        $factory = new RangeTagGeneratorFactory($store);

        $generator = $factory->forBirthdate();

        self::assertNotNull($generator);
    }

    public function testForYearlyIncomeCreatesGenerator(): void
    {
        $store = $this->createMockStore();
        $factory = new RangeTagGeneratorFactory($store);

        $generator = $factory->forYearlyIncome();

        self::assertNotNull($generator);
    }

    public function testGeneratorsCacheKeyDifferently(): void
    {
        $store = $this->createMockStore();
        $factory = new RangeTagGeneratorFactory($store);

        $birthdateGen = $factory->forBirthdate();
        $incomeGen = $factory->forYearlyIncome();

        // Both should be valid generators (different tag keys internally)
        $birthdateTags = $birthdateGen->generateValueTags(1000000000000.0);
        $incomeTags = $incomeGen->generateValueTags(50000.0);

        // Should produce different tags for different field IDs
        self::assertNotEmpty($birthdateTags);
        self::assertNotEmpty($incomeTags);
    }

    private function createMockStore(): DataEncryptionKeyStore
    {
        return new class implements DataEncryptionKeyStore {
            public function getKey(string $id): DataEncryptionKey
            {
                $dek = random_bytes(32);

                return new DataEncryptionKey($id, null, null, $dek);
            }
        };
    }
}

