<?php

declare(strict_types=1);

use App\Encryption\Encryptor;

require __DIR__ . '/../vendor/autoload.php';

$dek = random_bytes(32); // 32-byte secret key for AES-256.
$plaintext = 'Hello World';

$encryptor = new Encryptor();

function display(string $ciphertext): void
{
    echo base64_encode($ciphertext) . PHP_EOL;
}

echo "\nRandom encryption:\n";
$random1 = $encryptor->encryptRandom($plaintext, $dek);
$random2 = $encryptor->encryptRandom($plaintext, $dek);
display($random1);
display($random2);

echo "\nDeterministic encryption:\n";
$deterministic1 = $encryptor->encryptDeterministic($plaintext, $dek);
$deterministic2 = $encryptor->encryptDeterministic($plaintext, $dek);
display($deterministic1);
display($deterministic2);

assert($plaintext === $encryptor->decrypt($random1, $dek));
assert($plaintext === $encryptor->decrypt($deterministic1, $dek));
