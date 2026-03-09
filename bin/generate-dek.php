#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate a secure Data Encryption Key (DEK) for AES-256 encryption.
 *
 * Usage:
 *   php bin/generate-dek.php
 *
 * Output:
 *   A 64-character hexadecimal string (32 bytes when decoded)
 *   suitable for use as DATA_ENCRYPTION_KEY in .env files
 */

$dek = random_bytes(32);
$dekHex = bin2hex($dek);

echo "\n";
echo "Generated Data Encryption Key (DEK):\n";
echo "=====================================\n";
echo $dekHex . "\n";
echo "\n";
echo "Add this to your .env file:\n";
echo "DATA_ENCRYPTION_KEY=" . $dekHex . "\n";
echo "\n";
echo "⚠️  IMPORTANT:\n";
echo "- Keep this key SECRET and SECURE\n";
echo "- Never commit it to version control\n";
echo "- Use different keys for dev/staging/production\n";
echo "- Store in secure key management system for production\n";
echo "\n";

