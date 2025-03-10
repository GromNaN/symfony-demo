<?php

namespace App\Automapper;

use ApiPlatform\Metadata\ApiResource;
use AutoMapper\Attribute\MapFrom;
use AutoMapper\Attribute\Mapper;
use AutoMapper\Attribute\MapTo;
use Symfony\Component\Validator\Constraints\NotBlank;

#[ApiResource(
    shortName: 'automapper_planes',
    provider: AutomapperState::class,
    processor: AutomapperState::class,
)]
#[Mapper(source: 'array', target: 'array')]
class AutomapperPlane
{
    #[MapFrom(
        priority: 1,
        source: 'array',
        property: '_id',
        transformer: [BSONTransformer::class, 'transformObjectIdToString'],
    )]
    #[MapTo(
        priority: 1,
        target: 'array',
        property: '_id',
        transformer: [BSONTransformer::class, 'transformStringToObjectId'],
    )]
    #[MapTo(target: 'array', ignore: true)]
    public string $id;

    #[NotBlank]
    public string $name;

    #[NotBlank]
    #[MapFrom(
        source: 'array',
        property: 'created_at',
        transformer: [BSONTransformer::class, 'transformUTCDateTimeToDateTimeImmutable'],
    )]
    #[MapTo(
        target: 'array',
        property: 'created_at',
        transformer: [BSONTransformer::class, 'transformDateTimeToUTCDateTime'],
    )]
    #[MapTo(target: 'array', ignore: true)]
    public \DateTimeInterface $createdAt;
}
