<?php

namespace Functional;


use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class StationApiTest extends ApiTestCase
{
    public function testGetStation(): void
    {
        $response = static::createClient()->request('GET', '/api/stations/27120002');
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id' => 27120002,
        ]);
    }

    public function testGetStations(): void
    {
        $response = static::createClient()->request('GET', '/api/stations');
        self::assertResponseIsSuccessful();
    }
}