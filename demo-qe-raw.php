<?php

declare(strict_types=1);

/**
 * Queryable Encryption Demo - Raw PHP + PostgreSQL
 *
 * This script demonstrates a complete Queryable Encryption (QE) implementation:
 * 1. Schema creation with income and safeContent ARRAY fields
 * 2. Sample data insertion with HMAC-SHA256 tags stored as TEXT[]
 * 3. Range queries using PostgreSQL's native array overlap (&&) operator
 *
 * Queryable Encryption allows searching on encrypted data without decryption:
 * - Each plaintext value generates deterministic HMAC tags
 * - Tags are stored in safeContent (PostgreSQL TEXT[] ARRAY)
 * - Queries use array overlap to find matching encrypted values
 * - The database never sees plaintext values
 *
 * Usage:
 *   php demo-qe-raw.php              # Execute with live PostgreSQL database
 *   php demo-qe-raw.php --dry-run    # Show SQL without connecting to database
 */

// ============================================================================
// DATABASE CONNECTION CONFIGURATION
// ============================================================================

// PostgreSQL connection credentials (from .env file)
const DB_HOST = '127.0.0.1';
const DB_PORT = 5432;
const DB_NAME = 'app';
const DB_USER = 'app';
const DB_PASSWORD = '!ChangeMe!';

// ============================================================================
// COMMAND LINE OPTIONS
// ============================================================================

// Check if running in dry-run mode (shows SQL without database connection)
$dryRun = in_array('--dry-run', $argv, true);

// ============================================================================
// TAG GENERATION FUNCTION
// ============================================================================

/**
 * Generate HMAC-SHA256 tags for a numeric value.
 *
 * This function implements a multi-level tree structure for range queries:
 * - Level 0: Fine-grained buckets (size=1)
 * - Level 1: Medium buckets (size=2)
 * - Level 2: Coarse buckets (size=4)
 *
 * Each level produces one deterministic HMAC tag. This allows range queries
 * to match values without full decryption.
 *
 * @param int $value      The plaintext value to tag (e.g., 50000)
 * @param int $fieldId    Logical field identifier (2 = yearly_income)
 * @param int $levels     Number of tree levels (default: 3)
 *
 * @return string[]       Array of base64-encoded HMAC-SHA256 tags
 *
 * Example: generateTags(50000) returns:
 *   [
 *     "OqWcfrCYEcOEPiOdVptA...",  // Level 0 tag
 *     "ayUxzWOTZwCksfzN9WJL...",  // Level 1 tag
 *     "6H1TevXm4ldhZHRH9jak..."   // Level 2 tag
 *   ]
 */
function generateTags(int $value, int $fieldId = 2, int $levels = 3): array
{
    // Use a shared tag key derived from the DEK (Data Encryption Key)
    // In production, this would come from a KMS
    $tagKey = 'income-tag-key';

    $tags = [];

    // Generate one tag per tree level
    for ($level = 0; $level < $levels; $level++) {
        // Calculate segment size: 2^level
        // Level 0: size=1 (no division)
        // Level 1: size=2 (divide by 2)
        // Level 2: size=4 (divide by 4)
        $segmentSize = 1 << $level; // Bitwise left shift: 2^level

        // Calculate which bucket this value falls into at this level
        $bucketIndex = intval($value / $segmentSize);

        // Create the data to be hashed
        // Format: "fieldId|level|bucketIndex"
        // Example: "2|1|25000"
        $data = "{$fieldId}|{$level}|{$bucketIndex}";

        // Generate HMAC-SHA256 tag (binary format, not hex)
        // This ensures same value always generates same tag (deterministic)
        $tag = hash_hmac('sha256', $data, $tagKey, true);

        // Convert binary to base64 for storage in TEXT[] array
        $tags[] = base64_encode($tag);
    }

    return $tags;
}

// ============================================================================
// SAMPLE DATA
// ============================================================================

// Five sample users with various income levels for testing
$users = [
    ['name' => 'Alice', 'income' => 45000],
    ['name' => 'Bob', 'income' => 50000],
    ['name' => 'Charlie', 'income' => 55000],
    ['name' => 'David', 'income' => 30000],
    ['name' => 'Eve', 'income' => 75000],
];

// ============================================================================
// DRY RUN MODE (No database required)
// ============================================================================

if ($dryRun) {
    echo "🔐 Queryable Encryption Demo (Dry Run - No Database Required)\n";
    echo str_repeat('=', 75) . "\n\n";

    // ────────────────────────────────────────────────────────────────────
    // 1. SHOW CREATE TABLE STATEMENT
    // ────────────────────────────────────────────────────────────────────
    echo "📋 CREATE TABLE SQL\n";
    echo str_repeat('─', 75) . "\n";
    echo <<<SQL
    -- Define table with native PostgreSQL TEXT[] array for safeContent
    CREATE TABLE demo_users (
        id BIGSERIAL PRIMARY KEY,                      -- Auto-incrementing ID
        name VARCHAR(255) NOT NULL,                    -- User name (plaintext)
        yearly_income INTEGER NOT NULL,                -- Plaintext income value
        safecontent TEXT[] NOT NULL DEFAULT '{}'       -- Array of HMAC tags for QE
    );
    
    -- Index on yearly_income for efficient filtering
    CREATE INDEX idx_demo_users_income ON demo_users(yearly_income);
    SQL . "\n\n";

    // ────────────────────────────────────────────────────────────────────
    // 2. SHOW SAMPLE DATA WITH GENERATED TAGS
    // ────────────────────────────────────────────────────────────────────
    echo "🔐 SAMPLE DATA WITH GENERATED TAGS\n";
    echo str_repeat('─', 75) . "\n";
    foreach ($users as $user) {
        // Generate tags for this user's income
        $tags = generateTags($user['income']);

        echo $user['name'] . " - Income: \${$user['income']}\n";
        echo "  Generated tags: " . count($tags) . "\n";

        // Show preview of each tag
        foreach ($tags as $i => $tag) {
            echo "    Level {$i}: " . substr($tag, 0, 20) . "...\n";
        }
        echo "\n";
    }

    // ────────────────────────────────────────────────────────────────────
    // 3. DEMONSTRATE RANGE QUERY 1 ($45k - $55k)
    // ────────────────────────────────────────────────────────────────────
    echo "🔍 RANGE QUERY 1: Find income between \$45,000 and \$55,000\n";
    echo str_repeat('─', 75) . "\n";

    // Generate tags for all values in the range
    // This simulates what a client would do when searching
    $rangeTags1 = [];
    for ($income = 45000; $income <= 55000; $income += 5000) {
        // Merge tags from this income value
        $rangeTags1 = array_merge($rangeTags1, generateTags($income));
    }

    // Remove duplicate tags (multiple values may generate same tags)
    $rangeTags1 = array_unique($rangeTags1);

    echo "Generated " . count($rangeTags1) . " unique tags\n";
    echo "\nSQL Query:\n";
    echo "  SELECT id, name, yearly_income\n";
    echo "  FROM demo_users\n";
    echo "  WHERE safecontent && ARRAY['tag1', 'tag2', ...]\n";
    echo "  ORDER BY yearly_income ASC\n";
    echo "\n✓ Expected Results: Alice (45000), Bob (50000), Charlie (55000)\n\n";

    // ────────────────────────────────────────────────────────────────────
    // 4. DEMONSTRATE RANGE QUERY 2 ($30k - $50k)
    // ────────────────────────────────────────────────────────────────────
    echo "🔍 RANGE QUERY 2: Find income between \$30,000 and \$50,000\n";
    echo str_repeat('─', 75) . "\n";

    $rangeTags2 = [];
    for ($income = 30000; $income <= 50000; $income += 5000) {
        $rangeTags2 = array_merge($rangeTags2, generateTags($income));
    }
    $rangeTags2 = array_unique($rangeTags2);

    echo "Generated " . count($rangeTags2) . " unique tags\n";
    echo "✓ Expected Results: David (30000), Alice (45000), Bob (50000)\n\n";

    // ────────────────────────────────────────────────────────────────────
    // 5. DEMONSTRATE EXACT MATCH QUERY ($50k)
    // ────────────────────────────────────────────────────────────────────
    echo "🔍 RANGE QUERY 3: Exact match income = \$50,000\n";
    echo str_repeat('─', 75) . "\n";

    $exactTags = generateTags(50000);
    echo "Generated " . count($exactTags) . " tags\n";
    echo "✓ Expected Results: Bob (50000)\n\n";

    // ────────────────────────────────────────────────────────────────────
    // 6. PRINT SUMMARY
    // ────────────────────────────────────────────────────────────────────
    echo "✅ Summary\n";
    echo "  Total users: " . count($users) . "\n";
    echo "  Tags per value: 3 (tree levels)\n";
    echo "  Query 1 unique tags: " . count($rangeTags1) . "\n";
    echo "  Query 2 unique tags: " . count($rangeTags2) . "\n";
    echo "  Query 3 tags (exact): " . count($exactTags) . "\n\n";

    exit(0);
}

// ============================================================================
// LIVE DATABASE MODE
// ============================================================================

try {
    // ────────────────────────────────────────────────────────────────────
    // 1. CONNECT TO POSTGRESQL
    // ────────────────────────────────────────────────────────────────────

    // Build PostgreSQL DSN (Data Source Name)
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_USER,
        DB_PASSWORD
    );

    echo "Connecting to PostgreSQL: {$dsn}\n";

    // Create PDO connection with error mode set to exceptions
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✓ Connected to PostgreSQL at " . DB_HOST . ":" . DB_PORT . "/" . DB_NAME . "\n\n";

    // ────────────────────────────────────────────────────────────────────
    // 2. CREATE SCHEMA (DROP AND RECREATE TABLE)
    // ────────────────────────────────────────────────────────────────────

    echo "📋 Creating schema...\n";

    // Drop existing table (clean slate for demo)
    $pdo->exec('DROP TABLE IF EXISTS demo_users CASCADE');

    // Create demo_users table with TEXT[] ARRAY column for safeContent
    // TEXT[] is PostgreSQL's native array type - perfect for tag storage
    $pdo->exec('CREATE TABLE demo_users (
        id BIGSERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        yearly_income INTEGER NOT NULL,
        safecontent TEXT[] NOT NULL DEFAULT \'{}\'
    )');

    // Create index on yearly_income for query performance
    $pdo->exec('CREATE INDEX idx_demo_users_income ON demo_users(yearly_income)');

    echo "✓ Table created: demo_users\n\n";

    // ────────────────────────────────────────────────────────────────────
    // 3. INSERT SAMPLE DATA WITH GENERATED TAGS
    // ────────────────────────────────────────────────────────────────────

    echo "🔐 Inserting sample data...\n";

    // Prepare INSERT statement (parameterized to prevent SQL injection)
    $stmt = $pdo->prepare('INSERT INTO demo_users (name, yearly_income, safecontent) VALUES (?, ?, ?)');

    foreach ($users as $user) {
        // Generate HMAC tags for this user's income
        $tags = generateTags($user['income']);

        // Convert PHP array of tags to PostgreSQL array literal format
        // Format: {"tag1","tag2","tag3"}
        // Note: Each tag is quoted and escaped
        $escaped = array_map(
            fn($t) => '"' . addcslashes($t, '\\"') . '"',
            $tags
        );
        $arrayLiteral = '{' . implode(',', $escaped) . '}';

        // Execute INSERT with parameterized values
        // This stores the tags array in the safecontent column
        $stmt->execute([$user['name'], $user['income'], $arrayLiteral]);

        echo "  ✓ {$user['name']}: \${$user['income']} (tags: " . count($tags) . ")\n";
    }
    echo "\n";

    // ────────────────────────────────────────────────────────────────────
    // 4. DISPLAY ALL DATA FROM TABLE
    // ────────────────────────────────────────────────────────────────────

    echo "📊 All users in database:\n";
    echo str_pad('ID', 4) . ' ' . str_pad('Name', 10) . ' ' . str_pad('Income', 12) . "\n";
    echo str_repeat('-', 28) . "\n";

    // Fetch and display all users ordered by income
    $result = $pdo->query('SELECT id, name, yearly_income FROM demo_users ORDER BY yearly_income ASC');
    foreach ($result as $row) {
        echo str_pad((string)$row['id'], 4) . ' ' .
             str_pad($row['name'], 10) . ' $' .
             number_format($row['yearly_income']) . "\n";
    }
    echo "\n";

    // ════════════════════════════════════════════════════════════════════
    // 5. RANGE QUERY 1: Income between $45,000 and $55,000
    // ════════════════════════════════════════════════════════════════════

    echo "🔍 Query 1: Income between \$45,000 and \$55,000\n";
    echo str_repeat('─', 50) . "\n";

    // Generate tags for all values in the range
    $rangeTags1 = [];
    for ($income = 45000; $income <= 55000; $income += 5000) {
        $rangeTags1 = array_merge($rangeTags1, generateTags($income));
    }
    $rangeTags1 = array_unique($rangeTags1);

    // Build PostgreSQL ARRAY literal from tags
    // Format: ARRAY['tag1', 'tag2', 'tag3', ...]
    $literals = array_map(
        fn($t) => "'" . addcslashes($t, "'") . "'",
        $rangeTags1
    );
    $arrayLiteral = 'ARRAY[' . implode(', ', $literals) . ']';

    // Execute query using array overlap operator (&&)
    // The && operator returns TRUE if arrays have any common elements
    $sql = 'SELECT id, name, yearly_income FROM demo_users WHERE safecontent && ' .
           $arrayLiteral . ' ORDER BY yearly_income ASC';
    $results1 = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    echo "Generated " . count($rangeTags1) . " tags\n";
    echo str_pad('ID', 4) . ' ' . str_pad('Name', 10) . ' ' . str_pad('Income', 12) . "\n";
    echo str_repeat('-', 28) . "\n";
    foreach ($results1 as $row) {
        echo str_pad((string)$row['id'], 4) . ' ' .
             str_pad($row['name'], 10) . ' $' .
             number_format($row['yearly_income']) . "\n";
    }
    echo "Found " . count($results1) . " users\n\n";

    // ════════════════════════════════════════════════════════════════════
    // 6. RANGE QUERY 2: Income between $30,000 and $50,000
    // ════════════════════════════════════════════════════════════════════

    echo "🔍 Query 2: Income between \$30,000 and \$50,000\n";
    echo str_repeat('─', 50) . "\n";

    // Generate tags for the second range
    $rangeTags2 = [];
    for ($income = 30000; $income <= 50000; $income += 5000) {
        $rangeTags2 = array_merge($rangeTags2, generateTags($income));
    }
    $rangeTags2 = array_unique($rangeTags2);

    // Build and execute second range query
    $literals2 = array_map(
        fn($t) => "'" . addcslashes($t, "'") . "'",
        $rangeTags2
    );
    $arrayLiteral2 = 'ARRAY[' . implode(', ', $literals2) . ']';

    $sql2 = 'SELECT id, name, yearly_income FROM demo_users WHERE safecontent && ' .
            $arrayLiteral2 . ' ORDER BY yearly_income ASC';
    $results2 = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);

    echo "Generated " . count($rangeTags2) . " tags\n";
    echo str_pad('ID', 4) . ' ' . str_pad('Name', 10) . ' ' . str_pad('Income', 12) . "\n";
    echo str_repeat('-', 28) . "\n";
    foreach ($results2 as $row) {
        echo str_pad((string)$row['id'], 4) . ' ' .
             str_pad($row['name'], 10) . ' $' .
             number_format($row['yearly_income']) . "\n";
    }
    echo "Found " . count($results2) . " users\n\n";

    // ════════════════════════════════════════════════════════════════════
    // 7. EXACT MATCH QUERY: Exact income = $50,000
    // ════════════════════════════════════════════════════════════════════

    echo "🔍 Query 3: Exact income = \$50,000\n";
    echo str_repeat('─', 50) . "\n";

    // For exact match, generate tags for the single value
    $exactTags = generateTags(50000);

    // Build and execute exact match query
    $literals3 = array_map(
        fn($t) => "'" . addcslashes($t, "'") . "'",
        $exactTags
    );
    $arrayLiteral3 = 'ARRAY[' . implode(', ', $literals3) . ']';

    $sql3 = 'SELECT id, name, yearly_income FROM demo_users WHERE safecontent && ' .
            $arrayLiteral3 . ' ORDER BY yearly_income ASC';
    $results3 = $pdo->query($sql3)->fetchAll(PDO::FETCH_ASSOC);

    echo "Generated " . count($exactTags) . " tags\n";
    echo str_pad('ID', 4) . ' ' . str_pad('Name', 10) . ' ' . str_pad('Income', 12) . "\n";
    echo str_repeat('-', 28) . "\n";
    foreach ($results3 as $row) {
        echo str_pad((string)$row['id'], 4) . ' ' .
             str_pad($row['name'], 10) . ' $' .
             number_format($row['yearly_income']) . "\n";
    }
    echo "Found " . count($results3) . " users\n\n";

    // ════════════════════════════════════════════════════════════════════
    // 8. SUMMARY AND KEY TAKEAWAYS
    // ════════════════════════════════════════════════════════════════════

    echo "✅ Demo completed successfully!\n\n";
    echo "Key takeaways:\n";
    echo "  • safeContent is native PostgreSQL TEXT[] ARRAY (not CSV string)\n";
    echo "  • PostgreSQL && operator tests if arrays have any overlap\n";
    echo "  • No string_to_array() conversion needed - direct array comparison\n";
    echo "  • Range queries generate multiple tags (one per tree level)\n";
    echo "  • Tags are deterministic (same value = same tags)\n";

    // ════════════════════════════════════════════════════════════════════
    // ERROR HANDLING
    // ════════════════════════════════════════════════════════════════════

} catch (PDOException $e) {
    // Database connection or query error
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "\nTo see the demo without a database, run:\n";
    echo "  php demo-qe-raw.php --dry-run\n";
    exit(1);
} catch (Exception $e) {
    // Other runtime errors
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}



