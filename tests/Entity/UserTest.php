<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Encryption\Encryptor;
use App\Entity\UserEncrypted;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

#[CoversClass(UserEncrypted::class)]
final class UserTest extends TestCase
{
    public function testEncryptedFieldsRoundTrip(): void
    {
        $encryptor = new Encryptor();
        $dek = random_bytes(32);

        $user = new UserEncrypted();
        $user->email = $encryptor->encryptDeterministic('sarah@example.test', $dek);
        $user->firstName = $encryptor->encryptRandom('Sarah', $dek);
        $user->lastName = $encryptor->encryptRandom('Connor', $dek);

        self::assertSame(
            'sarah@example.test',
            $encryptor->decrypt($user->email, $dek)
        );
        self::assertSame(
            'Sarah',
            $encryptor->decrypt($user->firstName, $dek)
        );
        self::assertSame(
            'Connor',
            $encryptor->decrypt($user->lastName, $dek)
        );
    }

    public function testDeterministicEmailProducesSameCiphertext(): void
    {
        $encryptor = new Encryptor();
        $dek = random_bytes(32);

        $userA = new UserEncrypted();
        $userB = new UserEncrypted();

        $userA->email = base64_encode($encryptor->encryptDeterministic('sarah@example.test', $dek));
        $userB->email = base64_encode($encryptor->encryptDeterministic('sarah@example.test', $dek));

        self::assertSame($userA->email, $userB->email);
    }

    public function testRandomNamesProduceDifferentCiphertext(): void
    {
        $encryptor = new Encryptor();
        $dek = random_bytes(32);

        $userA = new UserEncrypted();
        $userB = new UserEncrypted();

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

        $user = new UserEncrypted();
        $plainPassword = 'MySecurePassword123!';
        $user->password = $hasher->hash($plainPassword);

        self::assertNotSame($plainPassword, $user->password);
        self::assertTrue($hasher->verify($user->password, $plainPassword));
        self::assertFalse($hasher->verify($user->password, 'WrongPassword'));
    }
}
