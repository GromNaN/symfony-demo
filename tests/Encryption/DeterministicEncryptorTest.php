<?php

declare(strict_types=1);

namespace App\Tests\Encryption;

use App\Encryption\Encryptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Encryptor::class)]
final class DeterministicEncryptorTest extends TestCase
{
    public function testEncryptDecryptRoundTrip(): void
    {
        $encryptor = new Encryptor();
        $dek = random_bytes(32);
        $plaintext = 'Hello World';

        $payload = $encryptor->encryptDeterministic($plaintext, $dek);

        self::assertSame($plaintext, $encryptor->decrypt($payload, $dek));
    }

    public function testDeterministicEncryptionProducesSameOutput(): void
    {
        $encryptor = new Encryptor();
        $dek = random_bytes(32);
        $plaintext = 'Hello World';

        $payload1 = $encryptor->encryptDeterministic($plaintext, $dek);
        $payload2 = $encryptor->encryptDeterministic($plaintext, $dek);

        self::assertSame($payload1, $payload2);
    }
}
