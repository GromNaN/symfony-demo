<?php

namespace Functional\Recipe;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class BridgedEntityTest extends ApiTestCase
{
    private const BASE_URL = '/api/bridged_recipe_entities';

    public function testGetList()
    {
        $response = static::createClient()->request('GET', static::BASE_URL);
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'totalItems' => 90,
        ]);
    }
}
