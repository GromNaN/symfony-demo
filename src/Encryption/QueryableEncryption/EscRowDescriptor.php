<?php

declare(strict_types=1);

namespace App\Encryption\QueryableEncryption;

/**
 * EscRowDescriptor represents one ESC row generated during QE field encryption.
 */
final readonly class EscRowDescriptor
{
    public function __construct(
        public int $fieldId,
        public string $rangeTag,
        public int $valueLower,
        public int $valueUpper,
    ) {
    }
}
