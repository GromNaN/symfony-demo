<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Encryption\EncryptedType;
use App\Encryption\MetadataInjection;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserEntityTest extends KernelTestCase
{
    public function testEntityStoredEncrypted(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $entityManager->getConnection()->executeStatement('DELETE FROM user');

        $user = new User();
        $user->email = 'sarah@example.test';
        $user->firstName = 'Sarah';
        $user->lastName = 'Smith';
        $user->birthday = new \DateTimeImmutable('1990-01-01');
        $user->password = 'password';

        $entityManager->persist($user);
        $entityManager->flush();
        $entityManager->clear();

        $rawUser = $entityManager->getConnection()->executeQuery('SELECT * FROM user')->fetchAssociative();
        self::assertNotEquals('sarah@example.test', $rawUser['email']);
        self::assertNotEquals('Sarah', $rawUser['first_name']);
        self::assertNotEquals('Smith', $rawUser['last_name']);
        self::assertIsString($rawUser['birthday']);

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'sarah@example.test']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame('sarah@example.test', $user->email);
        self::assertSame('Sarah', $user->firstName);
        self::assertSame('Smith', $user->lastName);
        self::assertEquals(new \DateTimeImmutable('1990-01-01'), $user->birthday);

        $user = new User();
        $user->email = 'sarah@example.test';
        $user->firstName = 'Sarah2';
        $user->lastName = 'Smith2';
        $user->birthday = new \DateTimeImmutable('1990-01-01');
        $user->password = 'password';
        $entityManager->persist($user);

        try {
            $entityManager->flush();
            $this->fail('Expected unique constraint violation exception not thrown');
        } catch (UniqueConstraintViolationException $e) {
            self::assertStringContainsString('UNIQUE constraint failed: user.email', $e->getMessage());
        }
    }
}

