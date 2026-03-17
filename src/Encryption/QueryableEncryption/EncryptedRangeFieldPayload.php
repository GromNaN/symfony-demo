<?php

declare(strict_types=1);

namespace App\Encryption\QueryableEncryption;

/**
 * EncryptedRangeFieldPayload contains ciphertext, safe content tags and ESC row descriptors.
 */
final readonly class EncryptedRangeFieldPayload
{
    /**
     * @param list<string> $safeContentTags
     * @param list<EscRowDescriptor> $escRows
     */
    public function __construct(
        public string $ciphertext,
        public array $safeContentTags,
        public array $escRows,
    ) {
    }
}
