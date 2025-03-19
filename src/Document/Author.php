<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\EmbeddedDocument]
class Author
{
    #[ODM\Field]
    public string $name;

    #[ODM\ReferenceOne(targetDocument: User::class, inversedBy: 'recipes')]
    public ?User $user;
}
