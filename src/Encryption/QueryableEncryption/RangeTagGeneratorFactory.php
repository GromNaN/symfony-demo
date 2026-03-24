<?php

declare(strict_types=1);

namespace App\Encryption\QueryableEncryption;

use App\Encryption\DataEncryptionKey\DataEncryptionKeyStore;

/**
 * RangeTagGeneratorFactory creates pre-configured RangeTagGenerator instances for QE fields.
 */
final class RangeTagGeneratorFactory
{
    public function __construct(
        private readonly DataEncryptionKeyStore $dekStore,
    ) {
    }

    /**
     * Create a RangeTagGenerator for birthdate field queries.
     *
     * @return RangeTagGenerator
     */
    public function forBirthdate(): RangeTagGenerator
    {
        return new RangeTagGenerator(
            precision: 0,
            min: -25567.0,   // 1900-01-01 as days since 1970-01-01
            max: 47482.0,    // 2100-01-01 as days since 1970-01-01
            fieldId: 1,              // FIELD_BIRTHDATE
            dekStore: $this->dekStore,
            dekId: 'birthdate-tag-key'
        );
    }

    /**
     * Create a RangeTagGenerator for yearly income field queries.
     *
     * @return RangeTagGenerator
     */
    public function forYearlyIncome(): RangeTagGenerator
    {
        return new RangeTagGenerator(
            precision: 0,
            min: 0.0,
            max: 1000000.0,
            fieldId: 2,              // FIELD_YEARLY_INCOME
            dekStore: $this->dekStore,
            dekId: 'income-tag-key'
        );
    }
}
