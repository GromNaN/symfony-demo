<?php

namespace Functional\Recipe;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class SqlTest extends ApiTestCase
{
    private const BASE_URL = '/api/sql_recipes';

    public function testGet()
    {
        $response = static::createClient()->request('GET', static::BASE_URL.'/1');
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
        ]);
    }
}
