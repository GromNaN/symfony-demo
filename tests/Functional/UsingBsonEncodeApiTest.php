<?php

namespace Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\UsingBsonEncode\Plane;
use MongoDB\BSON\Document;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

class UsingBsonEncodeApiTest extends ApiTestCase
{
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
        $response = static::createClient()->request('POST', '/api/planes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'A380',
            ],
        ]);

        self::assertResponseIsUnprocessable();
    }

    public function testPostPlane(): void
    {
        $response = static::createClient()->request('POST', '/api/planes', [
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
        /** @var Client $client */
        $client = $this->getContainer()->get(Client::class);
        $collection = $client->selectCollection('api', 'planes');
        $collection->deleteMany([]);
        $collection->insertOne([
            '_id' => new ObjectId('60b5f1b3e4b0c5f3b3f3b3b3'),
            'name' => 'A380',
            'created_at' => new UTCDateTime(new \DateTimeImmutable('2021-06-01T00:00:00Z')),
        ]);

        $response = static::createClient()->request('GET', '/api/planes/60b5f1b3e4b0c5f3b3f3b3b3');
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id' => '60b5f1b3e4b0c5f3b3f3b3b3',
        ]);
    }

    public function testGetPlanes(): void
    {
        $response = static::createClient()->request('GET', '/api/planes');
        self::assertResponseIsSuccessful();
    }
}