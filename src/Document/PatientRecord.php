<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\EmbeddedDocument]
class PatientRecord
{
    #[ODM\Id]
    public ?string $id;

    public function __construct(
        #[ODM\Field]
        #[ODM\Encrypt(queryType: ODM\EncryptQuery::Equality)]
        public string $ssn,
        #[ODM\EmbedOne(targetDocument: PatientBilling::class)]
        #[ODM\Encrypt]
        public PatientBilling $billing,
        #[ODM\Field]
        #[ODM\Encrypt(queryType: ODM\EncryptQuery::Range, sparsity: 1, trimFactor: 4, min: 100, max: 2000)]
        public int $billingAmount,
    ) {
    }
}
