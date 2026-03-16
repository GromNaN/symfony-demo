# Local KMS Setup

This project now uses `App\Encryption\KeyManagement\LocalKms` (via `KmsInterface`) by default.

`LocalKms` reads a master key from:

- `env(file:MASTER_KEY_FILE)`

## 1) Create the master key file

From project root:

```sh
mkdir -p var/keys
openssl rand -hex 32 > var/keys/local_master.key
chmod 600 var/keys/local_master.key
```

The file content is the master key material used to wrap/unwrap DEKs.

## 2) Configure environment variable

Set `MASTER_KEY_FILE` to the file path:

```dotenv
MASTER_KEY_FILE=var/keys/local_master.key
```

This is already configured in:

- `.env`
- `.env.dev`
- `.env.test`

## 3) Verify configuration

Run a focused test:

```sh
./vendor/bin/phpunit tests/Encryption/KeyManagement/LocalKmsTest.php
```

## Notes

- `var/` is gitignored in this project, so the key file is not committed.
- This setup is suitable for local/dev and test usage.
- For production, use a managed KMS implementation (`HashicorpVaultKms`, cloud KMS, etc.).

