<?php

namespace App\Tests\Functional\Plane;

use App\Bson\Plane;
use MongoDB\BSON\Document;
use MongoDB\BSON\ObjectId;

class UsingBsonApiTestCase extends BaseApiTestCase
{
    public const BASE_URL = '/api/bson_planes';

    public function testBsonEncodeDecode(): void
    {
        $plane = new Plane();
        $plane->id = (string) (new ObjectId());
        $plane->name = 'A380';
        $plane->createdAt = new \DateTimeImmutable('2021-06-01T00:00:00Z');

        $result = Document::fromPHP($plane)->toPHP(['root' => Plane::class]);
        self::assertEquals($plane, $result);
    }
}
