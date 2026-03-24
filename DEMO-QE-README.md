# Queryable Encryption Demo - Raw PHP + SQL

This demo script shows how Queryable Encryption works with PostgreSQL array overlap queries.

## What It Does

1. **Schema Creation**: Creates a simple table with `yearly_income` (integer) and `safeContent` (text) fields
2. **Data Insertion**: Inserts 5 sample users with generated tags stored in `safeContent`
3. **Range Queries**: Performs 3 different queries using PostgreSQL's `&&` (array overlap) operator

## How It Works

### Tag Generation
Tags are generated using HMAC-SHA256:
```
HMAC-SHA256("fieldId|level|bucketIndex", "tag-key")
```

Each value generates multiple tags (one per tree level) for range queries.

### PostgreSQL Array Query
```sql
SELECT * FROM demo_users
WHERE string_to_array(safecontent, ',') && CAST(:tags AS text[])
```

This checks if the CSV tags in `safeContent` have **any overlap** with the query tags.

## Setup & Run

### Prerequisites
- PostgreSQL server running
- PHP CLI installed

### Configuration
Edit the connection parameters in the script:
```php
const DB_HOST = 'localhost';
const DB_PORT = 5432;
const DB_NAME = 'talk_encryption';
const DB_USER = 'postgres';
const DB_PASSWORD = 'postgres';
```

### Execute
```bash
php demo-qe.php
```

### Expected Output
```
✓ Connected to PostgreSQL

📋 Creating schema...
✓ Table created: demo_users (id, name, yearly_income, safecontent)

🔐 Generating tags and inserting sample data...
  ✓ Alice: income=45000, tags=3
  ✓ Bob: income=50000, tags=3
  ✓ Charlie: income=55000, tags=3
  ✓ David: income=30000, tags=3
  ✓ Eve: income=75000, tags=3

📊 All users in database:
ID   Name       Income     Tag Count
────────────────────────────────────
1    David      $30,000    3
2    Alice      $45,000    3
3    Bob        $50,000    3
4    Charlie    $55,000    3
5    Eve        $75,000    3

🔍 Range Query Demo
============================================================

Query 1: Find users with income between $45000 and $55000
─────────────────────────────────────────────────────────────────

Generated 9 tags for range query.

Sample tags (first 5):
  1. fqRnAZxIFXkfqR...
  2. VrQfPrQqVrQfPr...
  3. ...

Results:
ID   Name       Income      
────────────────────────────
2    Alice      $45,000     
3    Bob        $50,000     
4    Charlie    $55,000     

Found 3 users

Query 2: Find users with income between $30,000 and $50,000
─────────────────────────────────────────────────────────────────

ID   Name       Income      
────────────────────────────
1    David      $30,000     
2    Alice      $45,000     
3    Bob        $50,000     

Found 3 users

Query 3: Find user with exact income of $50,000
─────────────────────────────────────────────────────────────────

ID   Name       Income      
────────────────────────────
3    Bob        $50,000     

Found 1 users

✅ Demo completed successfully!
```

## Key Concepts

### safeContent Format
- **Storage**: Comma-separated, base64-encoded HMAC-SHA256 hashes
- **Example**: `"aBC123==,xyz789==,qwe456=="`
- **Type**: TEXT (Doctrine `SIMPLE_ARRAY`)

### Tag Generation
- Each numeric value generates 3 tags (one per tree level)
- Tags are deterministic (same value = same tags every time)
- Range queries generate tags for every increment in the range

### PostgreSQL Operators
- `string_to_array(text, delimiter)` - Convert CSV string to text[]
- `array1 && array2` - Returns true if arrays have any common elements
- `CAST(? AS text[])` - Type-cast parameter as text array

## Advanced Usage

### Change Range Increment
Modify the loop step:
```php
for ($income = $minIncome; $income <= $maxIncome; $income += 1000) {
    // Smaller step = more tags = better precision
}
```

### Change Tree Depth
Modify the `$levels` parameter:
```php
generateTags(50000, 2, 5)  // 5 levels instead of 3
// More levels = more tags = wider range support
```

### Use Real DEK
Replace the hardcoded key:
```php
$tagKey = file_get_contents('/path/to/dek');
$tag = hash_hmac('sha256', $data, $tagKey, true);
```

## Security Notes

⚠️ **This demo is for educational purposes only.**

Production implementation should:
- Use actual DEKs (Data Encryption Keys) from a KMS
- Derive tag keys from DEK material
- Encrypt the `yearly_income` values (store as ciphertext, not plaintext)
- Use PostgreSQL native arrays (not CSV strings)
- Implement proper access controls

## File Structure

- `demo-qe.php` - Main demo script (this file)
- Raw PHP - No vendor dependencies or Symfony
- Raw SQL - Standard PostgreSQL syntax
- No ORM - Direct PDO queries

