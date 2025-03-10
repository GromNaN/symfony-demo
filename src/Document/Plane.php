<?php

namespace App\Document;

use ApiPlatform\Doctrine\Odm\Filter\DateFilter;
use ApiPlatform\Doctrine\Odm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
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
    #[ApiFilter(SearchFilter::class, strategy: 'partial')]
    public string $name;

    #[ODM\Field(name: 'created_at')]
    #[NotBlank]
    #[ApiFilter(DateFilter::class)]
    public \DateTimeImmutable $createdAt;
}
