# Integration Tests

These tests use real infrastructure services.

## Vault KMS integration test

Start the Vault container first:

```sh
docker-compose up -d vault
```

Run only integration tests:

```sh
./vendor/bin/phpunit --group integration
```

Run only Vault KMS integration test:

```sh
./vendor/bin/phpunit tests/Integration/KeyManagement/HashicorpVaultKmsIntegrationTest.php
```

Optional environment variables:

```sh
export VAULT_ADDR=http://127.0.0.1:8200
export VAULT_TOKEN=root
export VAULT_TRANSIT_KEY_NAME=talk-encryption-master
```

