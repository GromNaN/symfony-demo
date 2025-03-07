<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\ApiResource\Station;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bridge\Doctrine\ManagerRegistry;

class OdmApiTest extends ApiTestCase
{
    public function setUp(): void
    {
        /** @var ManagerRegistry $managerRegistry */
        $managerRegistry = $this->getContainer()->get('doctrine_mongodb');
        /** @var DocumentManager $dm */
        $dm = $managerRegistry->getManagerForClass(Station::class);
    }
}
