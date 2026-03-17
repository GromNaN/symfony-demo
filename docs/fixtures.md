# Fixtures

This project includes Doctrine fixtures for both models:

- `App\Entity\User` via `App\DataFixtures\UserFixtures`
- `App\Entity\UserQe` via `App\DataFixtures\UserQeFixtures`

`App\DataFixtures\AppFixtures` is intentionally empty.

## What gets inserted

- `UserFixtures`: 3 users with email/firstName/lastName/birthday + hashed passwords.
- `UserQeFixtures`: 3 QE users with plaintext `birthdate` and `yearlyIncome`.
  - `QueryableEncryptionSubscriber` populates `birthdateCipher`, `yearlyIncomeCipher`, `safeContent`, and `users_esc` rows during flush.

## Load fixtures

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

If using test env and PostgreSQL, ensure the test DB exists first:

```bash
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:schema:update --env=test --force
```

