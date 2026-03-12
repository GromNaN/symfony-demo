# Vault KMS Setup (Direct Vault)

This document describes how to run Vault directly with Docker Compose and create a transit master key used by `App\Encryption\KeyManagement\HashicorpVaultKms`.

## What this setup does

- `vault` service runs a local dev Vault server.
- Your app calls Vault Transit API directly at `http://vault:8200`.

## 1) Start Vault

```sh
docker compose up -d vault
```

Optional: check logs

```sh
docker compose logs -f vault
```

## 2) Create transit engine and master key

Use the Vault CLI inside the `vault` container:

```sh
docker compose exec vault sh -lc 'export VAULT_ADDR=http://127.0.0.1:8200 VAULT_TOKEN=${VAULT_DEV_ROOT_TOKEN_ID:-root} && vault secrets enable transit || true'
docker compose exec vault sh -lc 'export VAULT_ADDR=http://127.0.0.1:8200 VAULT_TOKEN=${VAULT_DEV_ROOT_TOKEN_ID:-root} && vault write -f transit/keys/talk-encryption-master'
```

Verify:

```sh
docker compose exec vault sh -lc 'export VAULT_ADDR=http://127.0.0.1:8200 VAULT_TOKEN=${VAULT_DEV_ROOT_TOKEN_ID:-root} && vault read transit/keys/talk-encryption-master'
```

## 3) Quick API smoke test (direct Vault)

Encrypt directly against Vault:

```sh
curl -sS -X POST http://127.0.0.1:8200/v1/transit/encrypt/talk-encryption-master \
  -H 'X-Vault-Token: root' \
  -H 'Content-Type: application/json' \
  -d '{"plaintext":"aGVsbG8="}'
```

Decrypt directly against Vault (replace ciphertext):

```sh
curl -sS -X POST http://127.0.0.1:8200/v1/transit/decrypt/talk-encryption-master \
  -H 'X-Vault-Token: root' \
  -H 'Content-Type: application/json' \
  -d '{"ciphertext":"vault:v1:..."}'
```

## 4) Wire in Symfony service config

Example service definition:

```yaml
services:
  App\Encryption\KeyManagement\HashicorpVaultKms:
    arguments:
      $masterKeyId: '%env(VAULT_TRANSIT_KEY_NAME)%'
      $vaultBaseUrl: '%env(VAULT_ADDR)%'
      $vaultToken: '%env(VAULT_TOKEN)%'
```

Example env vars:

```dotenv
VAULT_ADDR=http://vault:8200
VAULT_TOKEN=root
VAULT_TRANSIT_KEY_NAME=talk-encryption-master
```

## Notes

- This is a local dev setup using Vault dev mode and a static root token.
- Do not use this token strategy in production.
- In production, use AppRole/Kubernetes auth and a hardened Vault deployment.
