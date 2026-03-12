# Key Management System (KMS) Integration

## Overview

A Key Management System (KMS) is a centralized service that manages encryption keys. Instead of storing Data Encryption Keys (DEK) directly in environment variables or files, you request them from a KMS service.

This document explains how to integrate AWS KMS, Google Cloud KMS, HashiCorp Vault, or a local master key system into your talk-encryption application.

---

## Architecture

### Without KMS (Current)
```
Application → ENV['DATA_ENCRYPTION_KEY'] → Encrypt/Decrypt
```

### With KMS
```
Application → KMS Client → KMS Service (AWS/GCP/Vault/Local)
                ↓
            (Decrypt Master Key)
                ↓
            Return DEK
                ↓
Application → Use DEK → Encrypt/Decrypt Data
```

---

## Key Concepts

### Master Key (MK)
- **Purpose**: Encrypts the Data Encryption Key (DEK)
- **Location**: Stored securely in KMS service
- **You never see it**: The KMS service handles encryption/decryption with it
- **Example**: `arn:aws:kms:us-east-1:123456789:key/12345678-1234-1234-1234-123456789012`

### Data Encryption Key (DEK)
- **Purpose**: Encrypts your actual data (emails, names, etc.)
- **Generated locally**: You generate DEK locally in your application
- **Storage**: Stored **encrypted** in the database
- **Format**: `base64(MasterKey.encrypt(plaintext-DEK))`
- **Lifecycle**: Generated once per encryption context, then encrypted and persisted

### Plain DEK (in memory)
- **Purpose**: Temporary plaintext DEK used for encrypt/decrypt operations
- **Delivery**: Retrieved by asking KMS to decrypt the encrypted DEK from database
- **Ephemeral**: Only in memory during active encryption/decryption
- **Example**: `32-byte-key` (temporary, never persisted)

---

## Implementation Patterns

### Pattern 1: Request DEK on Every Operation (Simple, Recommended)

```
Request → KMS: "Give me DEK for key-alias/user-encryption"
         ← Response: plaintext DEK in memory
         → Use DEK to encrypt/decrypt user data
         → Clear DEK from memory after operation
```

**Pros**: Simple, always fresh key material, KMS audit trail every operation
**Cons**: Network overhead, KMS rate limits possible

### Pattern 2: Cache DEK in Memory (Performance)

```
Request → Check memory cache for DEK
         → If not cached: Ask KMS for DEK
         → Store in encrypted memory (if possible)
         → Use DEK for multiple operations
         → Periodically refresh (e.g., every hour)
```

**Pros**: Fewer KMS calls, better performance
**Cons**: More complex, key material in memory longer

### Pattern 3: Envelope Encryption (At-Rest Security)

```
Store in DB: base64(MasterKey.encrypt(DEK))
             + encrypted data

Retrieve → KMS: "Decrypt this DEK envelope"
        ← Response: plaintext DEK
        → Decrypt data using DEK
```

**Pros**: Never transmit plaintext DEK over network
**Cons**: Most complex, slower (decrypt envelope + data)

---

## AWS KMS

### Setup

1. **Create a Key in AWS**
   ```bash
   aws kms create-key \
     --description "talk-encryption data key" \
     --region us-east-1
   ```
   Response: `KeyId: arn:aws:kms:us-east-1:123456789:key/12345678-...`

2. **Create an Alias** (easier to reference)
   ```bash
   aws kms create-alias \
     --alias-name alias/talk-encryption-dek \
     --target-key-id <KeyId>
   ```

3. **Grant IAM role permission**
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [
       {
         "Effect": "Allow",
         "Action": [
           "kms:Decrypt",
           "kms:GenerateDataKey"
         ],
         "Resource": "arn:aws:kms:us-east-1:123456789:key/12345678-..."
       }
     ]
   }
   ```

### PHP Client Code

```php
use Aws\Kms\KmsClient;
use Aws\Exception\AwsException;

class AwsKmsStore implements DataEncryptionKeyStore
{
    private KmsClient $client;
    private string $keyId;

    public function __construct(string $keyId, string $region = 'us-east-1')
    {
        $this->keyId = $keyId;
        $this->client = new KmsClient([
            'version' => 'latest',
            'region'  => $region,
        ]);
    }

    public function getKey(string $id): DataEncryptionKey
    {
        try {
            $result = $this->client->generateDataKey([
                'KeyId'   => $this->keyId,
                'KeySpec' => 'AES_256',
            ]);

            // $result['Plaintext'] is the DEK (32 bytes)
            // $result['CiphertextBlob'] is the encrypted DEK (for storage)

            return new DataEncryptionKey(
                masterKeyId: $this->keyId,
                plaintext: base64_encode($result['Plaintext']),
                encrypted: base64_encode($result['CiphertextBlob'])
            );
        } catch (AwsException $e) {
            throw new \RuntimeException("KMS getKey failed: {$e->getMessage()}");
        }
    }

    public function decryptEnvelope(string $encryptedDek): string
    {
        try {
            $result = $this->client->decrypt([
                'CiphertextBlob' => base64_decode($encryptedDek, true),
            ]);

            return base64_encode($result['Plaintext']);
        } catch (AwsException $e) {
            throw new \RuntimeException("KMS decrypt failed: {$e->getMessage()}");
        }
    }
}
```

### Configuration (Symfony)

```yaml
# config/services.yaml
services:
    AwsKmsStore:
        class: App\Encryption\DataEncryptionKey\AwsKmsStore
        arguments:
            $keyId: '%env(AWS_KMS_KEY_ID)%'
            $region: '%env(AWS_REGION)%'

    DataEncryptionKeyStore:
        alias: AwsKmsStore
```

```bash
# .env or AWS Secrets Manager
AWS_KMS_KEY_ID=arn:aws:kms:us-east-1:123456789:key/12345678-...
AWS_REGION=us-east-1
```

---

## Google Cloud KMS

### Setup

1. **Create a Keyring and Key**
   ```bash
   gcloud kms keyrings create talk-encryption --location us-central1

   gcloud kms keys create user-encryption \
     --location us-central1 \
     --keyring talk-encryption \
     --purpose encryption
   ```

2. **Grant IAM role**
   ```bash
   gcloud kms keys add-iam-policy-binding user-encryption \
     --location us-central1 \
     --keyring talk-encryption \
     --member serviceAccount:app@project.iam.gserviceaccount.com \
     --role roles/cloudkms.cryptoKeyEncrypterDecrypter
   ```

### PHP Client Code

```php
use Google\Cloud\Kms\V1\KeyManagementServiceClient;
use Google\Cloud\Kms\V1\EncryptRequest;
use Google\Cloud\Kms\V1\DecryptRequest;

class GcpKmsStore implements DataEncryptionKeyStore
{
    private KeyManagementServiceClient $client;
    private string $keyName;

    public function __construct(
        string $projectId,
        string $location,
        string $keyring,
        string $key
    ) {
        $this->client = new KeyManagementServiceClient();
        $this->keyName = $this->client->cryptoKeyName(
            $projectId,
            $location,
            $keyring,
            $key
        );
    }

    public function getKey(string $id): DataEncryptionKey
    {
        // GCP doesn't have "generateDataKey" like AWS
        // Instead, you generate locally and encrypt it

        $dek = random_bytes(32); // Generate DEK locally

        // Encrypt it with the master key
        $encrypted = $this->encryptDek($dek);

        return new DataEncryptionKey(
            masterKeyId: $this->keyName,
            plaintext: base64_encode($dek),
            encrypted: base64_encode($encrypted)
        );
    }

    private function encryptDek(string $plaintext): string
    {
        $request = new EncryptRequest([
            'name'      => $this->keyName,
            'plaintext' => $plaintext,
        ]);

        $response = $this->client->encrypt($request);
        return $response->getCiphertext();
    }

    public function decryptEnvelope(string $encryptedDek): string
    {
        $request = new DecryptRequest([
            'name'       => $this->keyName,
            'ciphertext' => base64_decode($encryptedDek, true),
        ]);

        $response = $this->client->decrypt($request);
        return base64_encode($response->getPlaintext());
    }
}
```

---

## HashiCorp Vault

### Setup

1. **Start Vault**
   ```bash
   vault server -dev
   export VAULT_ADDR='http://127.0.0.1:8200'
   export VAULT_TOKEN='s.xxxxx'
   ```

2. **Create Transit Engine**
   ```bash
   vault secrets enable transit

   vault write -f transit/keys/talk-encryption-key
   ```

3. **Create Policy**
   ```hcl
   path "transit/encrypt/talk-encryption-key" {
     capabilities = ["update"]
   }

   path "transit/decrypt/talk-encryption-key" {
     capabilities = ["update"]
   }
   ```

### PHP Client Code

```php
use GuzzleHttp\Client as HttpClient;

class VaultKmsStore implements DataEncryptionKeyStore
{
    private HttpClient $client;
    private string $vaultAddr;
    private string $vaultToken;
    private string $keyName;

    public function __construct(
        string $vaultAddr,
        string $vaultToken,
        string $keyName
    ) {
        $this->vaultAddr = $vaultAddr;
        $this->vaultToken = $vaultToken;
        $this->keyName = $keyName;
        $this->client = new HttpClient();
    }

    public function getKey(string $id): DataEncryptionKey
    {
        $dek = random_bytes(32);

        $encrypted = $this->encryptDek(base64_encode($dek));

        return new DataEncryptionKey(
            masterKeyId: $this->keyName,
            plaintext: base64_encode($dek),
            encrypted: $encrypted
        );
    }

    private function encryptDek(string $plaintext): string
    {
        $response = $this->client->post(
            "{$this->vaultAddr}/v1/transit/encrypt/{$this->keyName}",
            [
                'headers' => [
                    'X-Vault-Token' => $this->vaultToken,
                ],
                'json' => ['plaintext' => $plaintext],
            ]
        );

        $data = json_decode($response->getBody(), true);
        return $data['data']['ciphertext'];
    }

    public function decryptEnvelope(string $encryptedDek): string
    {
        $response = $this->client->post(
            "{$this->vaultAddr}/v1/transit/decrypt/{$this->keyName}",
            [
                'headers' => [
                    'X-Vault-Token' => $this->vaultToken,
                ],
                'json' => ['ciphertext' => $encryptedDek],
            ]
        );

        $data = json_decode($response->getBody(), true);
        return $data['data']['plaintext'];
    }
}
```

---

## Local Master Key (Development / Single-Server)

For development or single-server deployments, you can use a local master key stored in a secure file.

### Setup

```bash
# Generate a master key (256 bits)
openssl rand -hex 32 > /etc/talk-encryption/master.key
chmod 600 /etc/talk-encryption/master.key
```

### PHP Client Code

```php
class LocalMasterKeyStore implements DataEncryptionKeyStore
{
    private string $masterKey;

    public function __construct(string $masterKeyPath)
    {
        if (!is_readable($masterKeyPath)) {
            throw new \RuntimeException("Master key not readable: {$masterKeyPath}");
        }

        $this->masterKey = hex2bin(trim(file_get_contents($masterKeyPath)));

        if (strlen($this->masterKey) !== 32) {
            throw new \RuntimeException('Master key must be 32 bytes');
        }
    }

    /**
     * Generate a new plaintext DEK and encrypt it with the local Master Key.
     */
    public function generateAndEncryptDek(): DataEncryptionKey
    {
        $plaintext = random_bytes(32); // Generate DEK locally
        $encrypted = $this->encryptDek($plaintext);

        return new DataEncryptionKey(
            masterKeyId: 'local-master-key',
            plaintext: $plaintext, // raw bytes
            encrypted: $encrypted // encrypted bytes
        );
    }

    /**
     * Encrypt plaintext DEK with the local Master Key.
     */
    private function encryptDek(string $plaintext): string
    {
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $iv . $ciphertext; // IV + ciphertext
    }

    /**
     * Decrypt an encrypted DEK using the local Master Key.
     */
    public function decryptDek(string $encryptedDek): string
    {
        $iv = substr($encryptedDek, 0, 16);
        $ciphertext = substr($encryptedDek, 16);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plaintext === false) {
            throw new \RuntimeException('DEK decryption failed');
        }

        return $plaintext; // raw bytes
    }
}
```

---

## Integration with Encryptor

### Update EncryptedType to Use KMS

```php
// src/Encryption/EncryptedType.php

use App\Encryption\DataEncryptionKey\DataEncryptionKeyStore;

final class EncryptedType
{
    public function __construct(
        private readonly Type $parentType,
        private readonly DataEncryptionKeyStore $dekStore,
        private readonly bool $deterministic,
    ) {
        $this->encryptor = new Encryptor();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        $parentValue = $this->parentType->convertToDatabaseValue($value, $platform);

        if ($parentValue === null) {
            return null;
        }

        // Fetch DEK from KMS
        $key = $this->dekStore->getKey('default');
        $dek = hex2bin($key->plaintext);

        $payload = $this->deterministic
            ? $this->encryptor->encryptDeterministic($parentValue, $dek)
            : $this->encryptor->encryptRandom($parentValue, $dek);

        return base64_encode($payload);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null || $value === '') {
            return $this->parentType->convertToPHPValue(null, $platform);
        }

        // Fetch DEK from KMS
        $key = $this->dekStore->getKey('default');
        $dek = hex2bin($key->plaintext);

        $payload = base64_decode($value, true);
        $plaintext = $this->encryptor->decrypt($payload, $dek);

        return $this->parentType->convertToPHPValue($plaintext, $platform);
    }
}
```

## Integration with Encryptor

### Database Schema for Encrypted DEK

You need a table to store encrypted DEKs:

```sql
CREATE TABLE data_encryption_keys (
    id VARCHAR(255) PRIMARY KEY,
    encrypted_dek LONGBLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Update EncryptedType to Use KMS

```php
// src/Encryption/EncryptedType.php

use App\Encryption\DataEncryptionKey\DataEncryptionKeyStore;

final class EncryptedType
{
    public function __construct(
        private readonly Type $parentType,
        private readonly DataEncryptionKeyStore $dekStore,
        private readonly bool $deterministic,
        private readonly string $dekId = 'default', // Which DEK to use
    ) {
        $this->encryptor = new Encryptor();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        $parentValue = $this->parentType->convertToDatabaseValue($value, $platform);

        if ($parentValue === null) {
            return null;
        }

        // Fetch encrypted DEK from DB, decrypt via KMS to get plaintext DEK
        $encryptedDek = $this->getEncryptedDekFromDatabase($this->dekId);
        $plainDek = $this->dekStore->decryptDek($encryptedDek);

        $payload = $this->deterministic
            ? $this->encryptor->encryptDeterministic($parentValue, $plainDek)
            : $this->encryptor->encryptRandom($parentValue, $plainDek);

        return base64_encode($payload);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null || $value === '') {
            return $this->parentType->convertToPHPValue(null, $platform);
        }

        // Fetch encrypted DEK from DB, decrypt via KMS to get plaintext DEK
        $encryptedDek = $this->getEncryptedDekFromDatabase($this->dekId);
        $plainDek = $this->dekStore->decryptDek($encryptedDek);

        $payload = base64_decode($value, true);
        $plaintext = $this->encryptor->decrypt($payload, $plainDek);

        return $this->parentType->convertToPHPValue($plaintext, $platform);
    }

    /**
     * Retrieve encrypted DEK from database by ID.
     * Implement this based on your storage layer.
     */
    private function getEncryptedDekFromDatabase(string $dekId): string
    {
        // Example pseudocode - implement based on your DB/ORM
        // $row = $this->connection->executeQuery(
        //     'SELECT encrypted_dek FROM data_encryption_keys WHERE id = ?',
        //     [$dekId]
        // )->fetchAssociative();
        // return $row['encrypted_dek'];
    }
}
```

### Symfony Configuration

```yaml
# config/services.yaml
services:
    # Choose one KMS implementation based on environment

    when@dev:
        LocalMasterKeyStore:
            class: App\Encryption\DataEncryptionKey\LocalMasterKeyStore
            arguments:
                $masterKeyPath: '%kernel.project_dir%/config/local.key'

        DataEncryptionKeyStore:
            alias: LocalMasterKeyStore

    when@prod:
        AwsKmsStore:
            class: App\Encryption\DataEncryptionKey\AwsKmsStore
            arguments:
                $keyId: '%env(AWS_KMS_KEY_ID)%'
                $region: '%env(AWS_REGION)%'

        DataEncryptionKeyStore:
            alias: AwsKmsStore
```

---

## Environment Variables

```bash
# For AWS
AWS_KMS_KEY_ID=arn:aws:kms:us-east-1:123456789:key/12345678-...
AWS_REGION=us-east-1

# For GCP
GCP_PROJECT_ID=my-project
GCP_LOCATION=us-central1
GCP_KEYRING=talk-encryption
GCP_KEY=user-encryption

# For Vault
VAULT_ADDR=http://127.0.0.1:8200
VAULT_TOKEN=s.xxxxxxxxxxxxx
VAULT_KEY_NAME=talk-encryption-key

# For Local
MASTER_KEY_PATH=/etc/talk-encryption/master.key
```

---

## Security Considerations

### 1. Never Log Keys
```php
// ❌ NEVER
\dump($dek);
\dd($plaintext);

// ✓ OK
\dump('DEK fetched from KMS');
```

### 2. Minimize Key Lifetime in Memory
```php
// Use scope blocks to limit key visibility
{
    $dek = $this->dekStore->getKey('default');
    $encrypted = $this->encryptor->encryptRandom($data, $dek);
    unset($dek); // Clear from memory
}
```

### 3. Audit KMS Calls
- AWS: CloudTrail logs all KMS operations
- GCP: Cloud Audit Logs
- Vault: Enable audit logging

### 4. Rotate Keys Regularly
- Change master key annually
- Use `GetKey()` with version ID if KMS supports it
- Re-encrypt old data with new master key

### 5. Access Control
- Restrict KMS key access to service accounts only
- Use IAM roles / policies, not static credentials
- Rotate API keys/tokens regularly

---

## Performance Optimization

### Caching DEK (Optional)

```php
class CachedDataEncryptionKeyStore implements DataEncryptionKeyStore
{
    private array $cache = [];
    private int $ttl = 3600; // 1 hour

    public function __construct(private DataEncryptionKeyStore $innerStore)
    {}

    public function getKey(string $id): DataEncryptionKey
    {
        $cacheKey = $id . ':' . time() / $this->ttl;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $key = $this->innerStore->getKey($id);
        $this->cache[$cacheKey] = $key;

        return $key;
    }
}
```

---

## Testing

### Mock KMS for Tests

```php
class MockDataEncryptionKeyStore implements DataEncryptionKeyStore
{
    private string $staticDek;

    public function __construct()
    {
        $this->staticDek = base64_encode(random_bytes(32));
    }

    public function getKey(string $id): DataEncryptionKey
    {
        return new DataEncryptionKey(
            masterKeyId: 'test-mock',
            plaintext: $this->staticDek
        );
    }
}
```

---

## Migration Path

### Step 1: Environment Variables (Current)
- Store `DATA_ENCRYPTION_KEY` in `.env`
- No KMS involvement

### Step 2: Local Master Key
- Store master key in `/etc/talk-encryption/master.key`
- Use `LocalMasterKeyStore`
- Better than environment variable

### Step 3: Cloud KMS
- AWS KMS / GCP KMS / Vault
- Automated key rotation
- Compliance audit trails
- Multi-region failover (optional)

---

## Checklist

- [ ] Choose KMS provider (AWS/GCP/Vault/Local)
- [ ] Implement `DataEncryptionKeyStore` for your provider
- [ ] Configure Symfony DI to wire the store
- [ ] Update environment variables
- [ ] Test encryption/decryption end-to-end
- [ ] Set up audit logging
- [ ] Document key rotation procedure
- [ ] Train team on secure practices

