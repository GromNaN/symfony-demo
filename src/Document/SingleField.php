<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document]
class SingleField
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field]
    public string $clearValue;

    #[ODM\Field]
    public string $encryptedValue;
}