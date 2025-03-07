<?php

namespace App\Automapper;

use ApiPlatform\Metadata\ApiResource;
use AutoMapper\Attribute\MapFrom;
use AutoMapper\Attribute\Mapper;
use AutoMapper\Attribute\MapTo;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints\NotBlank;

#[ApiResource(
    shortName: 'automapper_planes',
    normalizationContext: ['groups' => ['api']],
    denormalizationContext: ['groups' => ['api']],
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
        ignore: false,
        groups: ['bson']
    )]
    #[MapTo(
        priority: 1,
        target: 'array',
        property: '_id',
        transformer: [BSONTransformer::class, 'transformStringToObjectId'],
        ignore: false,
        groups: ['bson']
    )]
    #[Groups(['api'])]
    public string $id;

    #[NotBlank]
    #[Groups(['bson', 'api'])]
    public string $name;

    #[NotBlank]
    #[MapFrom(
        source: 'array',
        property: 'created_at',
        transformer: [BSONTransformer::class, 'transformUTCDateTimeToDateTimeImmutable'],
        ignore: false,
        groups: ['bson']
    )]
    #[MapTo(
        target: 'array',
        property: 'created_at',
        transformer: [BSONTransformer::class, 'transformDateTimeToUTCDateTime'],
        ignore: false,
        groups: ['bson']
    )]
    #[Groups(['api'])]
    public \DateTimeInterface $createdAt;
}
