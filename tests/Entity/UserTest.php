<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Encryption\Encryptor;
use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    public function testEncryptedFieldsRoundTrip(): void
    {
        $encryptor = new Encryptor();
        $dek = random_bytes(32);

        $user = new User();
        $user->email = $encryptor->encryptDeterministic('sarah@example.test', $dek);
        $user->firstName = $encryptor->encryptRandom('Sarah', $dek);
        $user->lastName = $encryptor->encryptRandom('Connor', $dek);

        self::assertSame(
            'sarah@example.test',
            $encryptor->decrypt(base64_decode($user->email, true), $dek)
        );
        self::assertSame(
            'Sarah',
            $encryptor->decrypt(base64_decode($user->firstName, true), $dek) // Fabien
        );
        self::assertSame(
            'Connor',
            $encryptor->decrypt(base64_decode($user->lastName, true), $dek)
        );
    }

    public function testDeterministicEmailProducesSameCiphertext(): void
    {
        $encryptor = new Encryptor();
        $dek = random_bytes(32);

        $userA = new User();
        $userB = new User();

        $userA->email = base64_encode($encryptor->encryptDeterministic('sarah@example.test', $dek));
        $userB->email = base64_encode($encryptor->encryptDeterministic('sarah@example.test', $dek));

        self::assertSame($userA->email, $userB->email);
    }

    public function testRandomNamesProduceDifferentCiphertext(): void
    {
        $encryptor = new Encryptor();
        $dek = random_bytes(32);

        $userA = new User();
        $userB = new User();

        $userA->firstName = base64_encode($encryptor->encryptRandom('Sarah', $dek));
        $userB->firstName = base64_encode($encryptor->encryptRandom('Sarah', $dek));

        self::assertNotSame($userA->firstName, $userB->firstName);
    }

    public function testPasswordHashing(): void
    {
        $factory = new PasswordHasherFactory([
            'common' => ['algorithm' => 'bcrypt'],
        ]);
        $hasher = $factory->getPasswordHasher('common');

        $user = new User();
        $plainPassword = 'MySecurePassword123!';
        $user->password = $hasher->hash($plainPassword);

        self::assertNotSame($plainPassword, $user->password);
        self::assertTrue($hasher->verify($user->password, $plainPassword));
        self::assertFalse($hasher->verify($user->password, 'WrongPassword'));
    }
}
