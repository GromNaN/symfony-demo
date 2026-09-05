<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Gromnan\DoctrineEncrypt\Encryption\EncryptionService;
use Gromnan\DoctrineEncrypt\Encryption\StaticKeyProvider;
use Gromnan\DoctrineEncrypt\EventListener\EncryptionSubscriber;
use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
    protected EntityManager $entityManager;
    protected EncryptionService $encryptionService;
    protected StaticKeyProvider $keyProvider;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup encryption service
        $this->keyProvider = new StaticKeyProvider([
            'default' => base64_encode('default-key-32-bytes-long-test'),
            'user-key' => base64_encode('user-key-for-pii-data-32-bytes'),
            'payment-key' => base64_encode('payment-key-for-financial-32b'),
        ]);

        $this->encryptionService = new EncryptionService($this->keyProvider);

        // Setup Doctrine ORM
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Fixtures'],
            isDevMode: true
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->entityManager = new EntityManager($connection, $config);

        // Register encryption subscriber
        $encryptionSubscriber = new EncryptionSubscriber($this->encryptionService);
        $this->entityManager->getEventManager()->addEventSubscriber($encryptionSubscriber);

        // Create database schema
        $schemaTool = new SchemaTool($this->entityManager);
        $classes = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($classes);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }
}
