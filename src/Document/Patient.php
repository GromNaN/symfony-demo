<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'patients')]
class Patient
{
    #[ODM\Id]
    public ?string $id;

    public function __construct(
        #[ODM\Field]
        public string $patientName,
        #[ODM\Field]
        public int $patientId,
        #[ODM\EmbedOne(targetDocument: PatientRecord::class)]
        public PatientRecord $patientRecord,
    ) {
    }
}
