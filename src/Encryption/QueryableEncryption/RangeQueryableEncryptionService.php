<?php

declare(strict_types=1);

namespace App\Encryption\QueryableEncryption;

interface RangeQueryableEncryptionService
{
    public function encryptBirthdate(\DateTimeInterface $birthdate): EncryptedRangeFieldPayload;

    public function encryptYearlyIncome(int $amount): EncryptedRangeFieldPayload;
}
