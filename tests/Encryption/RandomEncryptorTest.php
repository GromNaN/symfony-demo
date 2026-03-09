<?php

declare(strict_types=1);

namespace App\Tests\Encryption;

use App\Encryption\Encryptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Encryptor::class)]
final class RandomEncryptorTest extends TestCase
{
    public function testEncryptDecryptRoundTrip(): void
    {
        $encryptor = new Encryptor();
        $dek = random_bytes(32);
        $plaintext = 'Hello World';

        $payload = $encryptor->encryptRandom($plaintext, $dek);

        self::assertSame($plaintext, $encryptor->decrypt($payload, $dek));
    }

    public function testRandomEncryptionProducesDifferentOutputs(): void
    {
        $encryptor = new Encryptor();
        $dek = random_bytes(32);
        $plaintext = 'Hello World';

        $payload1 = $encryptor->encryptRandom($plaintext, $dek);
        $payload2 = $encryptor->encryptRandom($plaintext, $dek);

        self::assertNotSame($payload1, $payload2);
    }
}
