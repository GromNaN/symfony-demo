<?php

namespace App\Encryption\DataEncryptionKey;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

class DatabaseDataEncryptionKeyStore implements DataEncryptionKeyStore
{
    public function __construct(private Connection $conn, private string $tableName = 'data_encryption_keys')
    {
    }

    public function getKey(string $id): DataEncryptionKey
    {
        $stmt = $this->conn->prepare('SELECT masterKeyId, encryptedKey FROM ' . $this->tableName . ' WHERE id = :id');
        $stmt->bindValue('id', $id);
        $result = $stmt->executeQuery();

        $key = $result->fetchAssociative();
        if (! $key) {
            throw new \RuntimeException(sprintf('The data encryption key with id "%s" does not exist.', $id));
        }

        return new DataEncryptionKey($key['masterKeyId'], $key['encryptedKey']);
    }

    public function updateSchema(Schema $schema): void
    {
        if (! $schema->hasTable($this->tableName)) {
            $table = $schema->createTable($this->tableName);
            $table->addColumn('id', 'string');
            $table->addColumn('masterKeyId', 'string');
            $table->addColumn('encryptedKey', 'string');
            $table->setPrimaryKey(['id']);
        }
    }
}