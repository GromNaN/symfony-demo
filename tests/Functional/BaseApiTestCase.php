<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;

abstract class BaseApiTestCase extends ApiTestCase
{
    public const BASE_URL = '';
    private Collection $collection;

    public function setUp(): void
    {
        if (!static::BASE_URL) {
            self::fail('BASE_URL is not set');
        }

        /** @var Client $client */
        $client = $this->getContainer()->get(Client::class);
        $this->collection = $client->selectCollection('api', 'planes');
        $this->collection->drop();
        $client->getDatabase('api')->createCollection('planes', [
            'validator' => [
                '$jsonSchema' => [
                    'bsonType' => 'object',
                    'required' => ['_id', 'name', 'created_at'],
                    'properties' => [
                        '_id' => ['bsonType' => 'objectId'],
                        'name' => ['bsonType' => 'string'],
                        'created_at' => ['bsonType' => 'date'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ]);

        $bulk = [];

        foreach (range(10, 99) as $i) {
            $bulk[] = ['insertOne' => [[
                '_id' => new ObjectId('60b5f1b3e4b0c5f3b3f3b3'.$i),
                'name' => 'A'.$i,
                'created_at' => new UTCDateTime(new \DateTimeImmutable('2021-06-01T00:00:00Z')),
            ]]];
        }

        $this->collection->bulkWrite($bulk);
    }

    public function testPostValidation(): void
    {
        $response = static::createClient()->request('POST', static::BASE_URL, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'A380',
            ],
        ]);

        self::assertResponseIsUnprocessable();
    }

    public function testPost(): void
    {
        $response = static::createClient()->request('POST', static::BASE_URL, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'A380',
                'createdAt' => '2021-06-01T00:00:00Z',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $id = $this->collection->findOne(['name' => 'A380'], ['projection' => ['_id' => 1]])['_id'];

        $response = static::createClient()->request('GET', static::BASE_URL.'/'.$id);
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id' => (string) $id,
            'name' => 'A380',
            'createdAt' => '2021-06-01T00:00:00+00:00',
        ]);
    }

    public function testGet(): void
    {
        $response = static::createClient()->request('GET', static::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312');
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id' => '60b5f1b3e4b0c5f3b3f3b312',
            'name' => 'A12',
            'createdAt' => '2021-06-01T00:00:00+00:00',
        ]);
    }

    public function testGetNotFound(): void
    {
        $response = static::createClient()->request('GET', static::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b3b3');
        self::assertResponseStatusCodeSame(404);
    }

    public function testGets(): void
    {
        $response = static::createClient()->request('GET', static::BASE_URL);
        self::assertResponseIsSuccessful();
    }

    public function testPatch(): void
    {
        $response = static::createClient()->request('PATCH', static::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'name' => 'A390',
                'createdAt' => '2023-06-01T00:00:00Z',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $response = static::createClient()->request('GET', static::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312');
        self::assertResponseIsSuccessful();

        self::assertJsonContains([
            'id' => '60b5f1b3e4b0c5f3b3f3b312',
            'name' => 'A390',
            'createdAt' => '2023-06-01T00:00:00+00:00',
        ]);
    }

    public function testDelete(): void
    {
        $response = static::createClient()->request('DELETE', static::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312');
        self::assertResponseIsSuccessful();

        $response = static::createClient()->request('GET', static::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312');
        self::assertResponseStatusCodeSame(404);

        $response = static::createClient()->request('DELETE', static::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312');
        // Should be idempotent
        // self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(404);
    }
}
