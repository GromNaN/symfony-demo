<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\UserQueryable;
use App\Tests\DoctrineTestCase;

final class QueryableEncryptionSubscriberTest extends DoctrineTestCase
{
    protected static function entityClasses(): array
    {
        return [UserQueryable::class];
    }

    public function testPrePersistBuildsCiphertextsSafeContent(): void
    {
        $user = new UserQueryable();
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
        self::assertNotEmpty($rawUser['safecontent']);

        // safeContent should be a non-empty string (serialized array)
        $safeContent = unserialize($rawUser['safecontent']);
        self::assertIsArray($safeContent);
        self::assertNotEmpty($safeContent, 'safeContent should contain tags');
    }

    public function testPreUpdateReplacesSafeContent(): void
    {
        $user = new UserQueryable();
        $user->birthdate = new \DateTimeImmutable('1990-01-01T00:00:00+00:00');
        $user->yearlyIncome = 42000;

        $this->em->persist($user);
        $this->em->flush();

        // Update with new values
        $user->birthdate = new \DateTimeImmutable('1995-05-05T00:00:00+00:00');
        $user->yearlyIncome = 65000;
        $this->em->flush();
        $this->em->clear();

        $updated = $this->em->find(UserQueryable::class, $user->id);
        self::assertNotNull($updated);

        // safeContent should have changed due to different values
        self::assertNotEmpty($updated->safeContent, 'safeContent should be populated');
    }
}



