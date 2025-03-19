<?php

namespace App\Document;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ApiResource(shortName: 'odm_user')]
#[ODM\Document]
class User
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field]
    public string $email;
}
