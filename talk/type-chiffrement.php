<?php

declare(strict_types=1);

/**
 * Implement encryption algorithms in the same way MongoDB CSFLE does
 */

// The Data Encryption Key must be persisted using a Key Management System
$dek = random_bytes(32); // Clé secrète de 32 octets pour AES-256

$plaintext = 'Hello World';

function randomEncrypt(string $plaintext, string $dek): string
{
    // 1. Générer un IV aléatoire (16 octets pour AES)
    $iv = random_bytes(16);

    // 2. Chiffrer
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $dek, OPENSSL_RAW_DATA, $iv);

    // 3. On stocke l'IV avec le message pour pouvoir déchiffrer plus tard
    return $iv . $ciphertext;
}

function deterministicEncrypt(string $plaintext, string $dek): string
{
    // 1. Utilisation d'un SIV fixe en calculant le HMAC-SHA-512 du texte en clair (plaintext).
    // 1. Utilisation d'un HMAC pour générer le SIV.
    // Cela lie l'IV à la clé secrète ET au message.
    // SIV = Synthetic Initialization Vector
    $hmac = hash('sha512', $plaintext, true);

    // 2. Tronque le HMAC pour obtenir la taille d'IV requise (16 octets pour AES-CBC)
    $iv = substr($hmac, 0, 16);

    // 3. Chiffrer
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $dek, OPENSSL_RAW_DATA, $iv);

    return $iv . $ciphertext;
}

function decrypt(string $payload, string $dek): string
{
    // 1. Extract the IV from the stored value
    $iv = substr($payload, 0, 16);
    $ciphertext = substr($payload, 16);

    return openssl_decrypt($ciphertext, 'aes-256-cbc', $dek, OPENSSL_RAW_DATA, $iv);
}

function display(string $cipherext): void
{
    echo base64_encode($cipherext) . PHP_EOL;
}

echo "\nChiffrement aléatoire : \n";
display(randomEncrypt($plaintext, $dek));
display(randomEncrypt($plaintext, $dek));

echo "\nChiffrement déterministe : \n";
display(deterministicEncrypt($plaintext, $dek));
display(deterministicEncrypt($plaintext, $dek));

assert($plaintext === decrypt(randomEncrypt($plaintext, $dek), $dek));
assert($plaintext === decrypt(deterministicEncrypt($plaintext, $dek), $dek));
