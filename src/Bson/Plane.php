<?php

namespace App\Bson;

use ApiPlatform\Metadata\ApiResource;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Serializable;
use MongoDB\BSON\Unserializable;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @phpstan-type PlaneBson array{_id: ObjectId, name: string, created_at: UTCDateTime} */
#[ApiResource(
    shortName: 'planes',
    provider: State::class,
    processor: State::class,
)]
class Plane implements Serializable, Unserializable
{
    public string $id;

    #[NotBlank]
    public string $name;

    #[NotBlank]
    public \DateTimeInterface $createdAt;

    /** @return PlaneBson */
    public function bsonSerialize(): array
    {
        return [
            '_id' => new ObjectId($this->id),
            'name' => $this->name,
            'created_at' => new UTCDateTime($this->createdAt),
        ];
    }

    /**
     * @param PlaneBson $data
     */
    public function bsonUnserialize(array $data): void
    {
        $this->id = (string) $data['_id'];
        $this->name = $data['name'];
        $this->createdAt = $data['created_at']->toDateTime();
    }
}
