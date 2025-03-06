<?php

namespace App\Automapper;

use ApiPlatform\Metadata\ApiResource;
use AutoMapper\Attribute\MapFrom;
use AutoMapper\Attribute\MapTo;
use Symfony\Component\Validator\Constraints\NotBlank;

#[ApiResource(shortName: 'automapper_planes', provider: AutomapperState::class, processor: AutomapperState::class)]
class AutomapperPlane
{
    #[MapFrom(
        source: 'array',
        property: '_id',
        transformer: [BSONTransformer::class, 'transformStringToObjectId'],
    )]
    #[MapTo(
        target: 'array',
        property: '_id',
        transformer: [BSONTransformer::class, 'transformObjectIdToSting'],
    )]
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
    public \DateTimeInterface $createdAt;
}
