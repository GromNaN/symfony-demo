# Queryable Encryption (QE) with RangeTagGenerator

This project now uses a simplified QE model based on:

- **Single `safeContent` column** (JSON array) per row containing binary tags (HMAC-SHA256)
- **`RangeTagGenerator`** for deterministic, searchable equality and range tags
- **No separate ESC/ECOC tables** — everything stored in `users.safecontent`

## Entities

- `App\Entity\UserQe` → table `users`
  - `id`, `birthdate_cipher`, `yearly_income_cipher`, `safecontent`
  - Plain fields `birthdate`, `yearlyIncome` (unmapped, for QE input)

## Key Classes

- `App\Encryption\QueryableEncryption\RangeTagGenerator`
  - Generates deterministic tags for any numeric value
  - Supports equality queries (e.g., `income == 50000`)
  - Supports range queries (e.g., `45000 < income < 55000`)
  - Configurable sparsity (1-8) and precision (decimals)

- `App\Encryption\QueryableEncryption\NaiveRangeQueryableEncryptionService`
  - Encrypts `birthdate` and `yearlyIncome` via `DekEncryptionService`
  - Generates tags via `RangeTagGenerator`
  - Returns ciphertext + safeContent tags

- `App\Encryption\QueryableEncryption\QueryableEncryptionSubscriber`
  - Doctrine listener on `preFlush`
  - Converts plaintext `birthdate`/`yearlyIncome` → ciphertext + tags
  - Populates `safeContent` array

## Query Example

### Equality (SQL)

Find users with specific income:

```sql
SELECT u.*
FROM users u
WHERE safeContent @> ?;  -- PostgreSQL JSONB contains
```

For `income = 50000`, pass the matching tag (HMAC result base64-encoded) as `safeContent` filter.

### Range Query (SQL)

Find users with income between 45,000 and 55,000:

```sql
SELECT u.*
FROM users u
WHERE (safeContent @> ?)  -- Match any tag for the range
   OR (safeContent @> ?)
   OR ...;
```

In application code:

```php
$generator = new RangeTagGenerator(
    sparsity: 4,
    precision: 0,
    min: 0.0,
    max: 1000000.0,
    fieldId: 2,
    tagKey: 'income-tag-key'
);

$tags = $generator->generateRangeQueryTags(45000, 55000);
// Now query users where safeContent contains any of these tags
```

## Notes

- Tags are binary (HMAC-SHA256 output), base64-encoded for storage in JSON
- Each tag encodes: `fieldId | level | bucketIndex`
- Sparsity controls the multi-level index tree (1-8 levels)
- Precision controls rounding (e.g., 2 decimals = 100x scale)
- No database schema changes needed beyond JSON support

## For Queries

To implement "45,000 < income < 55,000" directly in SQL (e.g., via repository method):

```php
// In UserQeRepository
public function findByIncomeRange(int $min, int $max): array
{
    $generator = new RangeTagGenerator(4, 0, 0.0, 1000000.0, 2, 'income-tag-key');
    $tags = $generator->generateRangeQueryTags($min, $max);
    
    $tagConditions = array_map(fn($t) => "safeContent @> ?", $tags);
    $conditions = implode(' OR ', $tagConditions);
    
    $qb = $this->createQueryBuilder('u');
    // WHERE (safeContent @> tags[0]) OR (safeContent @> tags[1]) OR ...
    return $qb->where($qb->expr()->orX(...$tagConditions))
        ->setParameters($tags)
        ->getQuery()
        ->getResult();
}
```

