# Queryable Encryption (QE) Relational Schema

This project now includes a MongoDB-QE-inspired relational schema with:

- one shared `safecontent` per row
- one shared `users_esc` table for all QE fields
- one shared `users_ecoc` table for all QE fields
- `field_id` to identify which logical field a tag/index row belongs to
- a Doctrine listener/subscriber that populates ciphertext + `safecontent` + ESC rows automatically before flush

## Entities

- `App\Entity\UserQe` → table `users`
- `App\Entity\UsersEsc` → table `users_esc`
- `App\Entity\UsersEcoc` → table `users_ecoc`

## Runtime services

- `App\Encryption\QueryableEncryption\RangeQueryableEncryptionService`
- `App\Encryption\QueryableEncryption\NaiveRangeQueryableEncryptionService`
- `App\Encryption\QueryableEncryption\QueryableEncryptionSubscriber`

The subscriber reads plaintext fields from `UserQe` (`birthdate`, `yearlyIncome`) and writes:

- `birthdateCipher`
- `yearlyIncomeCipher`
- `safeContent`
- related `UsersEsc` rows

## Field IDs

In `UsersEsc`:

- `FIELD_BIRTHDATE = 1`
- `FIELD_YEARLY_INCOME = 2`

## Notes

- `safecontent` is stored as JSON (`array` in PHP) to keep portability in Doctrine/DBAL.
- `value_lower`, `value_upper`, `compaction_epoch` use DBAL `bigint` and are represented as `string` in PHP for portability.
- This schema is additive and does not replace the existing `User` entity/table.
