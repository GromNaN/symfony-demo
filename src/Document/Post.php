<?php

namespace App\Document;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'posts')]
#[ApiResource]
class Post
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field]
    public string $author;

    #[ODM\Field]
    public string $title;

    // #[ODM\Field]
    // public string $body;

    #[ODM\Field(type: 'date')]
    public \DateTimeInterface $date;
}
