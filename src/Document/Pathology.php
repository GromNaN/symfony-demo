<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations\EmbeddedDocument;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Encrypt;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Field;

#[EmbeddedDocument]
#[Encrypt]
class Pathology
{
    public function __construct(
        #[Field]
        public string $name,
        #[Field]
        public \DateTimeImmutable $diagnosisDate
    ) {
    }
}