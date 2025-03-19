<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\EmbeddedDocument]
class Step
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field]
    public int $stepNumber;

    #[ODM\Field]
    public string $description;
}
