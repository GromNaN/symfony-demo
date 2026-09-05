<?php

namespace App\Encryption\DataEncryptionKey;

use App\Encryption\KeyManagement\KmsInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

/**
 * DatabaseDataEncryptionKeyStore loads DEKs from a relational persistence table.
 */
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

        return new DataEncryptionKey($id, $key['masterKeyId'], $key['encryptedKey']);
    }

    public function rotateKey(string $id, KmsInterface $currentKms, KmsInterface $newKms): DataEncryptionKey
    {
        $currentKey = $this->getKey($id);
        $currentKms->decrypt($currentKey);

        $rotatedKey = new DataEncryptionKey($currentKey->id, null, null, $currentKey->getPlainDek());
        $newKms->encrypt($rotatedKey);

        $updatedRows = $this->conn->update($this->tableName, [
            'masterKeyId' => $rotatedKey->getMasterKeyId(),
            'encryptedKey' => $rotatedKey->getEncryptedDek(),
        ], [
            'id' => $id,
        ]);

        if ($updatedRows !== 1) {
            throw new \RuntimeException(sprintf('Failed to rotate the data encryption key with id "%s".', $id));
        }

        return $rotatedKey;
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