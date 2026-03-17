<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\UserQe;
use App\Entity\UsersEcoc;
use App\Entity\UsersEsc;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class QueryableEncryptionSubscriberTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $this->em = $entityManager;

        $metadata = [
            $this->em->getClassMetadata(UserQe::class),
            $this->em->getClassMetadata(UsersEsc::class),
            $this->em->getClassMetadata(UsersEcoc::class),
        ];

        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $this->em->clear();
    }

    public function testPrePersistBuildsCiphertextsSafeContentAndEscRows(): void
    {
        $user = new UserQe();
        $user->birthdate = new \DateTimeImmutable('1990-01-01T00:00:00+00:00');
        $user->yearlyIncome = 42000;

        $this->em->persist($user);
        $this->em->flush();
        $userId = $user->id;
        $this->em->clear();

        $rawUser = $this->em->getConnection()->executeQuery('SELECT * FROM users WHERE id = :id', ['id' => $userId])->fetchAssociative();
        self::assertIsArray($rawUser);
        self::assertNotSame('', $rawUser['birthdate_cipher']);
        self::assertNotSame('', $rawUser['yearly_income_cipher']);
        self::assertNotSame('', $rawUser['safecontent']);

        $escRows = $this->em->getConnection()->executeQuery(
            'SELECT field_id, value_lower, value_upper FROM users_esc WHERE user_id = :id ORDER BY field_id ASC',
            ['id' => $userId]
        )->fetchAllAssociative();

        self::assertCount(2, $escRows);
        self::assertSame('1', (string) $escRows[0]['field_id']);
        self::assertSame('2', (string) $escRows[1]['field_id']);
        self::assertSame('42000', (string) $escRows[1]['value_lower']);
        self::assertSame('42000', (string) $escRows[1]['value_upper']);
    }

    public function testPreUpdateReplacesEscRowsAndSafeContent(): void
    {
        $user = new UserQe();
        $user->birthdate = new \DateTimeImmutable('1990-01-01T00:00:00+00:00');
        $user->yearlyIncome = 42000;

        $this->em->persist($user);
        $this->em->flush();

        $user->birthdate = new \DateTimeImmutable('1995-05-05T00:00:00+00:00');
        $user->yearlyIncome = 65000;
        $this->em->flush();

        $escRows = $this->em->getConnection()->executeQuery(
            'SELECT field_id, value_lower, value_upper FROM users_esc WHERE user_id = :id ORDER BY field_id ASC',
            ['id' => $user->id]
        )->fetchAllAssociative();

        self::assertCount(2, $escRows);
        self::assertSame('65000', (string) $escRows[1]['value_lower']);
        self::assertSame('65000', (string) $escRows[1]['value_upper']);
    }
}

