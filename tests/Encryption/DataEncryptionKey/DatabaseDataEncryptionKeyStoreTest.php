<?php

declare(strict_types=1);

namespace App\Tests\Encryption\DataEncryptionKey;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\DataEncryptionKey\DatabaseDataEncryptionKeyStore;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseDataEncryptionKeyStore::class)]
final class DatabaseDataEncryptionKeyStoreTest extends TestCase
{
    public function testGetKeyReturnsDataEncryptionKeyFromDatabase(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $store = new DatabaseDataEncryptionKeyStore($connection, 'data_encryption_keys');
        $this->createSchemaFromStore($store, $connection);

        $connection->insert('data_encryption_keys', [
            'id' => 'key-1',
            'masterKeyId' => 'master-1',
            'encryptedKey' => 'enc-xyz',
        ]);

        $key = $store->getKey('key-1');

        self::assertInstanceOf(DataEncryptionKey::class, $key);
        self::assertSame('master-1', $key->masterKeyId);
    }

    public function testGetKeyThrowsWhenIdDoesNotExist(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $store = new DatabaseDataEncryptionKeyStore($connection, 'data_encryption_keys');
        $this->createSchemaFromStore($store, $connection);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $store->getKey('missing-key');
    }

    public function testUpdateSchemaCreatesExpectedTable(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $store = new DatabaseDataEncryptionKeyStore($connection, 'data_encryption_keys');
        $schema = new Schema();

        $store->updateSchema($schema);

        self::assertTrue($schema->hasTable('data_encryption_keys'));
        $table = $schema->getTable('data_encryption_keys');
        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('masterKeyId'));
        self::assertTrue($table->hasColumn('encryptedKey'));

        // Applying generated SQL should succeed on the active platform.
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        self::assertTrue(true);
    }

    private function createSchemaFromStore(DatabaseDataEncryptionKeyStore $store, Connection $connection): void
    {
        $schema = new Schema();
        $store->updateSchema($schema);

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }
}
