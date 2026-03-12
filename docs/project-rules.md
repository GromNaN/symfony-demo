# Project Rules

## Coding Rules

- Getters must return concrete types when possible.
- If required data is missing, getters must throw explicit runtime exceptions.
- Setters are never fluent: setters must return `void`.

## DataEncryptionKey Rules

- A `DataEncryptionKey` must be instantiated with exactly one key representation:
  - `encryptedKey`, or
  - `decryptedKey`.
- Accessing `getEncryptedKey()` when only a decrypted key exists throws `RuntimeException`.
- Accessing `getDecryptedKey()` when only an encrypted key exists throws `RuntimeException`.

