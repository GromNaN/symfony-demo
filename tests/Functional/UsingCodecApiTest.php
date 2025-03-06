<?php

namespace Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\UsingBsonEncode\Plane;
use MongoDB\BSON\Document;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;

class UsingCodecApiTest extends ApiTestCase
{
    public const BASE_URL = '/api/codec_planes';
    private Collection $collection;

    public function setUp(): void
    {
        /** @var Client $client */
        $client = $this->getContainer()->get(Client::class);
        $this->collection = $client->selectCollection('api', 'planes');
        $this->collection->deleteMany([]);

        $bulk = [];

        foreach (range(10, 99) as $i) {
            $bulk[] = ['insertOne' => [[
                '_id' => new ObjectId('60b5f1b3e4b0c5f3b3f3b3' . $i),
                'name' => 'A' . $i,
                'created_at' => new UTCDateTime(new \DateTimeImmutable('2021-06-01T00:00:00Z')),
            ]]];
        }

        $this->collection->bulkWrite($bulk);
    }

    public function testBsonEncodeDecode(): void
    {
        $plane = new Plane();
        $plane->id = (string) (new ObjectId());
        $plane->name = 'A380';
        $plane->createdAt = new \DateTimeImmutable('2021-06-01T00:00:00Z');

        $result = Document::fromPHP($plane)->toPHP(['root' => Plane::class]);
        self::assertEquals($plane, $result);
    }

    public function testPostPlaneValidation(): void
    {
        $response = static::createClient()->request('POST', self::BASE_URL, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'A380',
            ],
        ]);

        self::assertResponseIsUnprocessable();
    }

    public function testPostPlane(): void
    {
        $response = static::createClient()->request('POST', self::BASE_URL, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'A380',
                'createdAt' => '2021-06-01T00:00:00Z',
            ],
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testGetPlane(): void
    {
        $response = static::createClient()->request('GET', self::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312');
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id' => '60b5f1b3e4b0c5f3b3f3b312',
        ]);
    }

    public function testGetPlaneNotFound(): void
    {
        $response = static::createClient()->request('GET', self::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b3b3');
        self::assertResponseStatusCodeSame(404);
    }

    public function testGetPlanes(): void
    {
        $response = static::createClient()->request('GET', self::BASE_URL);
        self::assertResponseIsSuccessful();
    }

    public function testPatchPlane(): void
    {
        $response = static::createClient()->request('PATCH', self::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'name' => 'A390',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $response = static::createClient()->request('GET', self::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312');
        self::assertResponseIsSuccessful();

        self::assertJsonContains([
            'name' => 'A390',
        ]);
    }

    public function testDeletePlane(): void
    {
        $response = static::createClient()->request('DELETE', self::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312');
        self::assertResponseIsSuccessful();

        $response = static::createClient()->request('GET', self::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312');
        self::assertResponseStatusCodeSame(404);

        $response = static::createClient()->request('DELETE', self::BASE_URL.'/60b5f1b3e4b0c5f3b3f3b312');
        // Should be idempotent
        // self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(404);
    }
}
