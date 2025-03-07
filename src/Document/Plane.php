<?php

namespace App\Document;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Validator\Constraints\NotBlank;

#[ODM\Document(collection: 'planes')]
#[ApiResource(shortName: 'odm_planes')]
class Plane
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field]
    #[NotBlank]
    public string $name;

    #[ODM\Field(name: 'created_at')]
    #[NotBlank]
    public \DateTimeImmutable $createdAt;
}
