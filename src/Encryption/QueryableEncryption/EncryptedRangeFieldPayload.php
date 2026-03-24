<?php

declare(strict_types=1);

namespace App\Encryption\QueryableEncryption;

/**
 * EncryptedRangeFieldPayload contains ciphertext and safeContent tags for a QE field.
 */
final readonly class EncryptedRangeFieldPayload
{
    /**
     * @param array<string> $safeContentTags Base64-encoded HMAC tags for safeContent storage
     */
    public function __construct(
        public string $ciphertext,
        public array $safeContentTags,
    ) {
    }
}
