<?php

namespace App\Bridged;

use ApiPlatform\Doctrine\Odm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use App\Document\Plane;
use AutoMapper\Attribute\MapTo;
use Symfony\Component\Validator\Constraints\NotBlank;

#[ApiResource(
    shortName: 'bridged_planes',
    stateOptions: new Options(
        documentClass: Plane::class,
    ),
    provider: BridgedState::class,
    processor: BridgedState::class,
)]
class BridgedPlane
{
    // The condition is necessary to prevent accessing the property when the source object is new
    // I should use distinct groups for creation and update.
    #[MapTo(target: Plane::class, if: 'source.id ?? false')]
    public string $id;

    #[NotBlank]
    public string $name;

    #[NotBlank]
    public \DateTimeImmutable $createdAt;
}
