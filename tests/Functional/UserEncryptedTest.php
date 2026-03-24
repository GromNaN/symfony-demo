<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\UserEncrypted;
use App\Tests\DoctrineTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserEncryptedTest extends DoctrineTestCase
{
    public function testEntityStoredEncrypted(): void
    {
        $user = new UserEncrypted();
        $user->email = 'sarah@example.test';
        $user->firstName = 'Sarah';
        $user->lastName = 'Smith';
        $user->birthday = new \DateTimeImmutable('1990-01-01');
        $user->password = 'password';

        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        $rawUser = $this->em->getConnection()->executeQuery('SELECT * FROM user_encrypted')->fetchAssociative();
        $rawUser = array_map(static fn (mixed $value): mixed => is_resource($value) ? stream_get_contents($value) : $value, $rawUser);
        self::assertNotEquals('sarah@example.test', $rawUser['email']);
        self::assertNotEquals('Sarah', $rawUser['first_name']);
        self::assertNotEquals('Smith', $rawUser['last_name']);
        self::assertIsString($rawUser['birthday']);

        $user = $this->em->getRepository(UserEncrypted::class)->findOneBy(['email' => 'sarah@example.test']);
        self::assertInstanceOf(UserEncrypted::class, $user);
        self::assertSame('sarah@example.test', $user->email);
        self::assertSame('Sarah', $user->firstName);
        self::assertSame('Smith', $user->lastName);
        self::assertEquals(new \DateTimeImmutable('1990-01-01'), $user->birthday);

        $user = new UserEncrypted();
        $user->email = 'sarah@example.test';
        $user->firstName = 'Sarah2';
        $user->lastName = 'Smith2';
        $user->birthday = new \DateTimeImmutable('1990-01-01');
        $user->password = 'password';
        $this->em->persist($user);

        try {
            $this->em->flush();
            $this->fail('Expected unique constraint violation exception not thrown');
        } catch (UniqueConstraintViolationException $e) {
            self::assertStringContainsString('email', $e->getMessage());
        }
    }

    public function testArrayHydrationReturnsDecryptedValues(): void
    {
        $user = new UserEncrypted();
        $user->email = 'array@example.test';
        $user->firstName = 'Array';
        $user->lastName = 'Hydration';
        $user->birthday = new \DateTimeImmutable('1992-02-02');
        $user->password = 'password';

        $this->em->persist($user);
        $this->em->flush();

        $userId = $user->id;
        $this->em->clear();

        $rows = $this->em
            ->createQuery('SELECT u FROM App\\Entity\\UserEncrypted u WHERE u.id = :id')
            ->setParameter('id', $userId)
            ->getArrayResult();

        dump($rows);
        self::assertCount(1, $rows);
        self::assertSame('array@example.test', $rows[0]['email']);
        self::assertSame('Array', $rows[0]['firstName']);
        self::assertSame('Hydration', $rows[0]['lastName']);

        $rawUser = $this->em->getConnection()->executeQuery('SELECT * FROM user_encrypted WHERE id = :id', ['id' => $userId])->fetchAssociative();
        self::assertNotEquals('array@example.test', $rawUser['email']);
        self::assertNotEquals('Array', $rawUser['first_name']);
        self::assertNotEquals('Hydration', $rawUser['last_name']);

        $rawUser = array_map(static fn (mixed $value): mixed => is_resource($value) ? stream_get_contents($value) : $value, $rawUser);

        dump($rawUser);
    }


    protected static function entityClasses(): array
    {
        return [UserEncrypted::class];
    }
}
