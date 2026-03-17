<?php

declare(strict_types=1);

namespace App\Tests\Encryption\QueryableEncryption;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\DataEncryptionKey\DataEncryptionKeyStore;
use App\Encryption\DekEncryptionService;
use App\Encryption\Encryptor;
use App\Encryption\QueryableEncryption\NaiveRangeQueryableEncryptionService;
use App\Entity\UsersEsc;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NaiveRangeQueryableEncryptionService::class)]
final class NaiveRangeQueryableEncryptionServiceTest extends TestCase
{
    public function testEncryptBirthdateBuildsPayload(): void
    {
        $service = new NaiveRangeQueryableEncryptionService($this->newDekEncryptionService());

        $payload = $service->encryptBirthdate(new \DateTimeImmutable('1990-01-01T00:00:00+00:00'));

        self::assertNotSame('', $payload->ciphertext);
        self::assertCount(1, $payload->safeContentTags);
        self::assertCount(1, $payload->escRows);
        self::assertSame(UsersEsc::FIELD_BIRTHDATE, $payload->escRows[0]->fieldId);
    }

    public function testEncryptYearlyIncomeBuildsPayload(): void
    {
        $service = new NaiveRangeQueryableEncryptionService($this->newDekEncryptionService());

        $payload = $service->encryptYearlyIncome(42000);

        self::assertNotSame('', $payload->ciphertext);
        self::assertCount(1, $payload->safeContentTags);
        self::assertCount(1, $payload->escRows);
        self::assertSame(UsersEsc::FIELD_YEARLY_INCOME, $payload->escRows[0]->fieldId);
        self::assertSame(42000, $payload->escRows[0]->valueLower);
        self::assertSame(42000, $payload->escRows[0]->valueUpper);
    }

    private function newDekEncryptionService(): DekEncryptionService
    {
        $dek = random_bytes(32);

        $store = new class($dek) implements DataEncryptionKeyStore {
            public function __construct(private readonly string $dek)
            {
            }

            public function getKey(string $id): DataEncryptionKey
            {
                return new DataEncryptionKey($id, null, null, $this->dek);
            }
        };

        return new DekEncryptionService($store, new Encryptor());
    }
}
