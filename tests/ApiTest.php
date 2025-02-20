<?php

namespace App\Tests\Utils;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;


class ApiTest extends ApiTestCase
{
    public function testGetPosts()
    {
        $client = static::createClient();
        $client->request('GET', '/api/posts');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(
            ['totalItems' => 30],
        );

        $client->request('GET', '/api/posts?publishedAt[before]=2017-07-01T00:00:00Z');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(
            ['totalItems' => 3],
        );

        $client->request('GET', '/api/posts?publishedAt[after]=2017-07-01T00:00:00Z');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(
            ['totalItems' => 27],
        );

        $client->request('GET', '/api/posts?publishedAt[before]=2017-07-05T00:00:00Z&publishedAt[after]=2017-07-01T00:00:00Z');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(
            ['totalItems' => 4],
        );

        $client->request('GET', '/api/posts?title=Mineralis');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(
            ['totalItems' => 1],
        );

        $client->request('GET', '/api/posts?summary=generis');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(
            ['totalItems' => 5],
        );
    }

    public function testInsertUpdateDelete()
    {
        $client = static::createClient();

        $client->request('POST', '/api/posts', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'title' => 'New post',
                'slug' => 'new-post',
                'summary' => 'New post summary',
                'content' => 'New post content',
                'publishedAt' => '2017-08-01T00:00:00Z',
                'author' => '/api/users/1',
            ],
        ]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'title' => 'New post',
            'slug' => 'new-post',
            'summary' => 'New post summary',
            'content' => 'New post content',
            'publishedAt' => '2017-08-01T00:00:00+00:00',
            'author' => '/api/users/1',
        ]);
        $id = $client->getResponse()->toArray()['id'];

        $client->request('GET', "/api/posts/$id");
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id' => $id,
            'title' => 'New post',
            'slug' => 'new-post',
            'summary' => 'New post summary',
            'content' => 'New post content',
            'publishedAt' => '2017-08-01T00:00:00+00:00',
            'author' => '/api/users/1',
        ]);

        $client->request('PATCH', "/api/posts/$id", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'title' => 'Updated post',
                'slug' => 'updated-post',
                'author' => '/api/users/2',
            ],
        ]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id' => $id,
            'title' => 'Updated post',
            'slug' => 'updated-post',
            'summary' => 'New post summary',
            'content' => 'New post content',
            'publishedAt' => '2017-08-01T00:00:00+00:00',
            'author' => '/api/users/2',
        ]);

        $client->request('DELETE', "/api/posts/$id");
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', "/api/posts/$id");
        self::assertResponseStatusCodeSame(404);
    }
}
