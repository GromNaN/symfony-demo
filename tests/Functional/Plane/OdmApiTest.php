<?php

namespace App\Tests\Functional\Plane;

use MongoDB\Builder\Pipeline;
use MongoDB\Builder\Search;
use MongoDB\Builder\Stage;

class OdmApiTest extends BaseApiTestCase
{
    public const BASE_URL = '/api/odm_planes';

    public function testSearchIndex()
    {
        $this->collection->createSearchIndex(['mappings' => ['dynamic' => true]]);
        do {
            usleep(1000);
            $index = $this->collection->listSearchIndexes(['name' => 'default'])->current();
        } while ('READY' !== $index['status']);

        $pipeline = [
            Stage::search(
                Search::queryString('name', 'A1*'),
            ),
            Stage::facet(
                documents: new Pipeline(
                    Stage::skip(1),
                    Stage::limit(5)
                ),
                count: new Pipeline(
                    Stage::count('count')
                ),
            ),
        ];
        $results = $this->collection->aggregate($pipeline)->toArray();
        $this->assertCount(10, $results);

        $response = static::createClient()->request('GET', static::BASE_URL.'?name=A2');
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'totalItems' => 10,
        ]);
    }
}
