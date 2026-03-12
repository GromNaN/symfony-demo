<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserEntityTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);

        $this->em = $entityManager;

        // Ensure each test starts from a clean table.
        $this->em->getConnection()->executeStatement('DELETE FROM user');
    }

    public function testEntityStoredEncrypted(): void
    {
        $user = new User();
        $user->email = 'sarah@example.test';
        $user->firstName = 'Sarah';
        $user->lastName = 'Smith';
        $user->birthday = new \DateTimeImmutable('1990-01-01');
        $user->password = 'password';

        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        $rawUser = $this->em->getConnection()->executeQuery('SELECT * FROM user')->fetchAssociative();
        self::assertNotEquals('sarah@example.test', $rawUser['email']);
        self::assertNotEquals('Sarah', $rawUser['first_name']);
        self::assertNotEquals('Smith', $rawUser['last_name']);
        self::assertIsString($rawUser['birthday']);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'sarah@example.test']);
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
        $this->em->persist($user);

        try {
            $this->em->flush();
            $this->fail('Expected unique constraint violation exception not thrown');
        } catch (UniqueConstraintViolationException $e) {
            self::assertStringContainsString('UNIQUE constraint failed: user.email', $e->getMessage());
        }
    }

    public function testArrayHydrationReturnsDecryptedValues(): void
    {
        $user = new User();
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
            ->createQuery('SELECT u.id, u.email, u.firstName, u.lastName FROM App\\Entity\\User u WHERE u.id = :id')
            ->setParameter('id', $userId)
            ->getArrayResult();

        self::assertCount(1, $rows);
        self::assertSame('array@example.test', $rows[0]['email']);
        self::assertSame('Array', $rows[0]['firstName']);
        self::assertSame('Hydration', $rows[0]['lastName']);

        $rawUser = $this->em->getConnection()->executeQuery('SELECT * FROM user WHERE id = :id', ['id' => $userId])->fetchAssociative();
        self::assertNotEquals('array@example.test', $rawUser['email']);
        self::assertNotEquals('Array', $rawUser['first_name']);
        self::assertNotEquals('Hydration', $rawUser['last_name']);
    }
}
