<?php

declare(strict_types=1);

namespace App\Encryption\QueryableEncryption;

use App\Encryption\DekEncryptionService;
use App\Entity\UsersEsc;

/**
 * NaiveRangeQueryableEncryptionService builds QE ciphertext/tags/ESC descriptors for demo usage.
 */
final class NaiveRangeQueryableEncryptionService implements RangeQueryableEncryptionService
{
    public function __construct(
        private readonly DekEncryptionService $dekEncryptionService,
    ) {
    }

    public function encryptBirthdate(\DateTimeInterface $birthdate): EncryptedRangeFieldPayload
    {
        $epochMillis = (int) $birthdate->format('Uv');
        $plaintext = $birthdate->format(\DateTimeInterface::ATOM);
        $ciphertext = $this->dekEncryptionService->encryptRandom('qe::birthdate', $plaintext);

        return new EncryptedRangeFieldPayload(
            $ciphertext,
            [base64_encode(hash('sha256', 'birthdate:eq:' . $epochMillis, true))],
            [
                new EscRowDescriptor(
                    UsersEsc::FIELD_BIRTHDATE,
                    hash('sha256', 'birthdate:range:' . $epochMillis, true),
                    $epochMillis,
                    $epochMillis,
                ),
            ],
        );
    }

    public function encryptYearlyIncome(int $amount): EncryptedRangeFieldPayload
    {
        $ciphertext = $this->dekEncryptionService->encryptRandom('qe::yearly_income', (string) $amount);

        return new EncryptedRangeFieldPayload(
            $ciphertext,
            [base64_encode(hash('sha256', 'yearly_income:eq:' . $amount, true))],
            [
                new EscRowDescriptor(
                    UsersEsc::FIELD_YEARLY_INCOME,
                    hash('sha256', 'yearly_income:range:' . $amount, true),
                    $amount,
                    $amount,
                ),
            ],
        );
    }
}
