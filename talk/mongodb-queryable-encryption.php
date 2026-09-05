<?php

declare(strict_types=1);

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Driver\ClientEncryption;
use MongoDB\Exception\Exception as MongoDBException;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

/**
 * MongoDB Queryable Encryption demo.
 *
 * What this script does:
 * 1) Builds a client with automatic encryption using encryptedFieldsMap
 * 2) Ensures a DEK exists in key vault
 * 3) Recreates the encrypted collection metadata + collection
 * 4) Inserts a document where some fields are encrypted/queryable
 * 5) Runs equality and range queries on encrypted fields
 *
 * Run:
 *   php talk/mongodb-queryable-encryption.php
 *   php talk/mongodb-queryable-encryption.php --dry-run
 */

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . '/.env';
if (class_exists(Dotenv::class) && is_file($envFile)) {
    (new Dotenv())->usePutenv(true)->loadEnv($envFile);
}

$options = parseCliOptions($argv);

$mongodbUri = envOrDefault('MONGODB_URI', 'mongodb://localhost:27017');
$databaseName = envOrDefault('MONGODB_DB', 'symfony');
$collectionName = 'users_qe_demo';
$namespace = sprintf('%s.%s', $databaseName, $collectionName);
$keyVaultNamespace = envOrDefault('MONGODB_KEY_VAULT_NAMESPACE', 'encryption.__keyVault');
$keyAltName = envOrDefault('MONGODB_QE_KEY_ALT_NAME', 'users-qe-demo-key');

$masterKeyBytes = loadLocalMasterKey($projectRoot);
$kmsProviders = ['local' => ['key' => base64_encode($masterKeyBytes)]];

if ($options['dryRun']) {
    echo "Dry run mode (no MongoDB calls).\n";
    echo "URI: {$mongodbUri}\n";
    echo "DB: {$databaseName}\n";
    echo "Collection: {$collectionName}\n";
    echo "Key vault namespace: {$keyVaultNamespace}\n";
    echo "Key alt name: {$keyAltName}\n";
    echo "Local KMS key length: " . strlen($masterKeyBytes) . " bytes\n";
    exit(0);
}

try {
    $keyVaultClient = new Client($mongodbUri);
    $clientEncryption = $keyVaultClient->createClientEncryption([
        'keyVaultNamespace' => $keyVaultNamespace,
        'kmsProviders' => $kmsProviders,
    ]);

    [$keyVaultDb, $keyVaultCollection] = explode('.', $keyVaultNamespace, 2);
    $keyVault = $keyVaultClient->selectCollection($keyVaultDb, $keyVaultCollection);

    // Required unique index for alternate names.
    $keyVault->createIndex(
        ['keyAltNames' => 1],
        [
            'unique' => true,
            'partialFilterExpression' => ['keyAltNames' => ['$exists' => true]],
        ]
    );

    $existingKey = $keyVault->findOne(['keyAltNames' => $keyAltName]);
    $keyId = $existingKey['_id'] ?? $clientEncryption->createDataKey('local', ['keyAltNames' => [$keyAltName]]);

    $encryptedFields = [
        'fields' => [
            [
                'path' => 'name',
                'bsonType' => 'string',
                'keyId' => $keyId,
            ],
            [
                'path' => 'email',
                'bsonType' => 'string',
                'keyId' => $keyId,
                'queries' => ['queryType' => 'equality'],
            ],
            [
                'path' => 'birthday',
                'bsonType' => 'date',
                'keyId' => $keyId,
                'queries' => [
                    'queryType' => 'range',
                    'sparsity' => 1,
                    'min' => new UTCDateTime(strtotime('1900-01-01T00:00:00Z') * 1000),
                    'max' => new UTCDateTime(strtotime('2100-01-01T00:00:00Z') * 1000),
                ],
            ],
        ],
    ];

    $encryptedClient = new Client(
        $mongodbUri,
        [],
        [
            'autoEncryption' => [
                'keyVaultNamespace' => $keyVaultNamespace,
                'kmsProviders' => $kmsProviders,
                'encryptedFieldsMap' => [$namespace => $encryptedFields],
                'extraOptions' => [
                    // Keeps the demo usable if crypt_shared isn't configured globally.
                    'cryptSharedLibRequired' => false,
                ],
            ],
        ]
    );

    $db = $keyVaultClient->selectDatabase($databaseName);

    // Drop collection and QE side collections created by the server.
    foreach ([$collectionName, "enxcol_.{$collectionName}.esc", "enxcol_.{$collectionName}.ecoc"] as $name) {
        try {
            $db->dropCollection($name);
        } catch (MongoDBException) {
            // Ignore if collection does not exist.
        }
    }

    $db->command([
        'create' => $collectionName,
        'encryptedFields' => $encryptedFields,
    ])->toArray();

    $collection = $encryptedClient->selectCollection($databaseName, $collectionName);

    $collection->insertOne([
        '_id' => 1,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'birthday' => new UTCDateTime(strtotime('1992-05-04T00:00:00Z') * 1000),
        'password' => password_hash('demo-password', PASSWORD_BCRYPT),
    ]);

    $exactMatch = $collection->findOne(['email' => 'alice@example.com']);

    $rangeMatches = $collection->find([
        'birthday' => [
            '$gte' => new UTCDateTime(strtotime('1990-01-01T00:00:00Z') * 1000),
            '$lt' => new UTCDateTime(strtotime('1995-01-01T00:00:00Z') * 1000),
        ],
    ])->toArray();

    echo "Inserted and queried document(s) successfully.\n\n";

    echo "Equality query on encrypted 'email':\n";
    echo json_encode(normalizeForJson($exactMatch), JSON_PRETTY_PRINT) . "\n\n";

    echo "Range query on encrypted 'birthday' (1990-01-01 <= x < 1995-01-01):\n";
    echo json_encode(normalizeForJson($rangeMatches), JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Queryable Encryption demo failed: {$e->getMessage()}\n");
    fwrite(STDERR, "Tip: ensure Atlas Local / MongoDB with QE support and crypt_shared are available.\n");
    exit(1);
}

/** @return array{dryRun: bool} */
function parseCliOptions(array $argv): array
{
    return [
        'dryRun' => in_array('--dry-run', $argv, true),
    ];
}

function envOrDefault(string $name, string $default): string
{
    $value = getenv($name);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function loadLocalMasterKey(string $projectRoot): string
{
    $base64Key = getenv('MONGODB_LOCAL_MASTERKEY_BASE64');
    if (is_string($base64Key) && $base64Key !== '') {
        $decoded = base64_decode($base64Key, true);
        if ($decoded !== false && strlen($decoded) === 96) {
            return $decoded;
        }

        throw new RuntimeException('MONGODB_LOCAL_MASTERKEY_BASE64 must decode to exactly 96 bytes.');
    }

    $path = envOrDefault('MASTER_KEY_FILE', $projectRoot . '/var/keys/local_master.key');
    if (!str_starts_with($path, '/')) {
        $path = $projectRoot . '/' . ltrim($path, '/');
    }

    if (!is_file($path)) {
        throw new RuntimeException("Local master key file not found: {$path}");
    }

    $raw = trim((string) file_get_contents($path));
    if ($raw === '') {
        throw new RuntimeException("Local master key file is empty: {$path}");
    }

    // Accept hex-encoded key material (project default) or raw key bytes.
    if (ctype_xdigit($raw) && strlen($raw) % 2 === 0) {
        $raw = (string) hex2bin($raw);
    }

    if (strlen($raw) === 96) {
        return $raw;
    }

    // MongoDB local KMS needs 96 bytes; derive deterministically for local demo usage.
    return hash_hkdf('sha256', $raw, 96, 'mongodb-local-kms-demo');
}

function normalizeForJson(mixed $value): mixed
{
    if ($value instanceof UTCDateTime) {
        return $value->toDateTime()->format(DATE_ATOM);
    }

    if ($value instanceof MongoDB\Model\BSONDocument || $value instanceof MongoDB\Model\BSONArray) {
        $value = $value->getArrayCopy();
    }

    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = normalizeForJson($item);
        }

        return $value;
    }

    return $value;
}
