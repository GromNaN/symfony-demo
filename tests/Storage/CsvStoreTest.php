<?php

namespace App\Tests\Storage;

use App\ApiResource\Station;
use App\Storage\CsvStore;
use PHPUnit\Framework\TestCase;

class CsvStoreTest extends TestCase
{
    public function testFind()
    {
        $id = 27120002;
        $store = $this->getStore();
        $result = $store->find(27120002);

        self::assertInstanceOf(Station::class, $result);
        self::assertEquals($id, $result->id);
    }

    public function testAll()
    {
        $store = $this->getStore();
        $result = $store->all(2);
        self::assertInstanceOf(\Iterator::class, $result);

        $result = iterator_to_array($result);

        self::assertCount(2, $result);
        self::assertInstanceOf(Station::class, $result[0]);
    }

    private function getStore(): CsvStore
    {
        return new CsvStore(__DIR__ . '/../../data/prix-des-carburants-en-france-flux-instantane-v2.csv');
    }
}