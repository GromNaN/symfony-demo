<?php

namespace App\Tests\Functional;

use App\Automapper\AutomapperPlane;
use AutoMapper\AutoMapper;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class UsingAutomapperApiTestCase extends BaseApiTestCase
{
    public const BASE_URL = '/api/automapper_planes';

    public function testMap(): void
    {
        $id = new ObjectId('60b5f1b4f3e3f0000f000000');
        $date = new \DateTimeImmutable('2021-06-01T00:00:00+00:00');

        /** @var AutoMapper $automapper */
        $automapper = $this->getContainer()->get('automapper');

        $plane = $automapper->map(['_id' => $id, 'name' => 'Boeing 747', 'created_at' => new UTCDateTime($date)], AutomapperPlane::class, ['groups' => ['bson']]);

        self::assertInstanceOf(AutomapperPlane::class, $plane);
        self::assertEquals('Boeing 747', $plane->name);
        self::assertEquals($date, $plane->createdAt);
        self::assertEquals('60b5f1b4f3e3f0000f000000', $plane->id);

        $data = $automapper->map($plane, 'array', ['groups' => ['bson']]);

        self::assertArrayHasKey('_id', $data);
        self::assertEquals($id, $data['_id']);
        self::assertArrayHasKey('name', $data);
        self::assertEquals('Boeing 747', $data['name']);
        self::assertArrayHasKey('created_at', $data);
        self::assertEquals(new UTCDateTime($date), $data['created_at']);
        self::assertArrayNotHasKey('id', $data);
        self::assertArrayNotHasKey('createdAt', $data);
    }
}
