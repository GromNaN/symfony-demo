<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\UserQueryable;
use App\Encryption\QueryableEncryption\RangeTagGeneratorFactory;
use App\Tests\DoctrineTestCase;

final class UserQeRepositoryTest extends DoctrineTestCase
{
    protected static function entityClasses(): array
    {
        return [UserQueryable::class];
    }

    public function getRangeTagGeneratorFactory(): RangeTagGeneratorFactory
    {
        return self::getContainer()->get(RangeTagGeneratorFactory::class);
    }

    public function testFindByIncomeRange(): void
    {
        $user1 = new UserQueryable();
        $user1->birthdate = new \DateTimeImmutable('1990-03-15T00:00:00+00:00');
        $user1->yearlyIncome = 45000;
        $this->em->persist($user1);

        $user2 = new UserQueryable();
        $user2->birthdate = new \DateTimeImmutable('1985-07-22T00:00:00+00:00');
        $user2->yearlyIncome = 50000;
        $this->em->persist($user2);

        $user3 = new UserQueryable();
        $user3->birthdate = new \DateTimeImmutable('1995-11-08T00:00:00+00:00');
        $user3->yearlyIncome = 55000;
        $this->em->persist($user3);

        $user4 = new UserQueryable();
        $user4->birthdate = new \DateTimeImmutable('2000-01-01T00:00:00+00:00');
        $user4->yearlyIncome = 30000;
        $this->em->persist($user4);

        $this->em->flush();
        $this->em->clear();

        $generator = $this->getRangeTagGeneratorFactory()->forYearlyIncome();
        $tags = $generator->generateRangeQueryTags(45000, 55000);

        $results = $this->queryByTags($tags);

        // Should find user1 (45000), user2 (50000), user3 (55000)
        self::assertCount(3, $results);
    }

    public function testFindByExactIncome(): void
    {
        $user = new UserQueryable();
        $user->birthdate = new \DateTimeImmutable('1990-03-15T00:00:00+00:00');
        $user->yearlyIncome = 50_000;
        $this->em->persist($user);
        $this->em->flush();

        $this->em->clear();

        $generator = $this->getRangeTagGeneratorFactory()->forYearlyIncome();
        $tags = $generator->generateValueTags(50000.0);

        $results = $this->queryByTags($tags);

        self::assertNotEmpty($results);
        self::assertEquals(50000, $results[0]->yearlyIncome);
    }

    public function testFindByIncomeRangeEmptyResult(): void
    {
        $user = new UserQueryable();
        $user->birthdate = new \DateTimeImmutable('1990-03-15T00:00:00+00:00');
        $user->yearlyIncome = 30000;
        $this->em->persist($user);
        $this->em->flush();

        $this->em->clear();

        $generator = $this->getRangeTagGeneratorFactory()->forYearlyIncome();
        $tags = $generator->generateRangeQueryTags(45000, 55000);

        $results = $this->queryByTags($tags);

        self::assertEmpty($results);
    }

    public function testFindByBirthdateRange(): void
    {
        $user1 = new UserQueryable();
        $user1->birthdate = new \DateTimeImmutable('1990-03-15T00:00:00+00:00');
        $user1->yearlyIncome = 45000;
        $this->em->persist($user1);

        $user2 = new UserQueryable();
        $user2->birthdate = new \DateTimeImmutable('1995-06-20T00:00:00+00:00');
        $user2->yearlyIncome = 50000;
        $this->em->persist($user2);

        $user3 = new UserQueryable();
        $user3->birthdate = new \DateTimeImmutable('2000-12-25T00:00:00+00:00');
        $user3->yearlyIncome = 30000;
        $this->em->persist($user3);

        $this->em->flush();
        $this->em->clear();

        $generator = $this->getRangeTagGeneratorFactory()->forBirthdate();
        $minDate = new \DateTimeImmutable('1989-01-01T00:00:00+00:00');
        $maxDate = new \DateTimeImmutable('1996-12-31T00:00:00+00:00');
        $minMillis = (int) $minDate->format('Uv');
        $maxMillis = (int) $maxDate->format('Uv');
        $tags = $generator->generateRangeQueryTags((float) $minMillis, (float) $maxMillis);

        $results = $this->queryByTags($tags);

        // Should find user1 (1990) and user2 (1995)
        self::assertCount(2, $results);
    }

    /**
     * Helper to query by tags using PostgreSQL array overlap operator.
     *
     * @param string[] $tags Binary tags (not yet base64 encoded)
     *
     * @return UserQueryable[]
     */
    private function queryByTags(array $tags): array
    {
        $encodedTags = array_map(static fn(string $t): string => base64_encode($t), $tags);

        if ($encodedTags === []) {
            return [];
        }

        $ids = $this->em->getConnection()->executeQuery(
            'SELECT id FROM user_queryable WHERE string_to_array(safecontent, \',\') && CAST(:tags AS text[]) ORDER BY id ASC',
            ['tags' => $this->toPostgresTextArrayLiteral($encodedTags)]
        )->fetchFirstColumn();

        if ($ids === []) {
            return [];
        }

        $entities = [];
        foreach ($ids as $id) {
            $entity = $this->em->find(UserQueryable::class, (int) $id);
            if ($entity !== null) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * @param list<string> $values
     */
    private function toPostgresTextArrayLiteral(array $values): string
    {
        $escaped = array_map(
            static fn(string $value): string => '"' . addcslashes($value, '\\"') . '"',
            $values
        );

        return '{' . implode(',', $escaped) . '}';
    }
}

