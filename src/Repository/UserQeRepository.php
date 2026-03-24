<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserQueryable;
use App\Encryption\QueryableEncryption\RangeTagGeneratorFactory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * UserQeRepository provides queryable encryption search methods for UserQe entities.
 *
 * @extends ServiceEntityRepository<UserQueryable>
 */
final class UserQeRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly RangeTagGeneratorFactory $generatorFactory,
    ) {
        parent::__construct($registry, UserQueryable::class);
    }

    /**
     * Find UserQe entities with yearly income in the given range.
     *
     * @param int $minIncome Inclusive lower bound (e.g., 45000)
     * @param int $maxIncome Inclusive upper bound (e.g., 55000)
     *
     * @return UserQueryable[]
     */
    public function findByIncomeRange(int $minIncome, int $maxIncome): array
    {
        $generator = $this->generatorFactory->forYearlyIncome();
        $tags = $generator->generateRangeQueryTags((float) $minIncome, (float) $maxIncome);

        if (empty($tags)) {
            return [];
        }

        return $this->queryByTags($tags);
    }

    /**
     * Find a single UserQe with exact yearly income value.
     *
     * @param int $income The exact income value to match
     *
     * @return UserQueryable|null
     */
    public function findByExactIncome(int $income): ?UserQueryable
    {
        $generator = $this->generatorFactory->forYearlyIncome();
        $tags = $generator->generateValueTags((float) $income);

        if (empty($tags)) {
            return null;
        }

        return $this->queryByTagsOne($tags);
    }

    /**
     * Find UserQe entities by birthdate range.
     *
     * @param \DateTimeInterface $minDate Inclusive lower bound
     * @param \DateTimeInterface $maxDate Inclusive upper bound
     *
     * @return UserQueryable[]
     */
    public function findByBirthdateRange(\DateTimeInterface $minDate, \DateTimeInterface $maxDate): array
    {
        $generator = $this->generatorFactory->forBirthdate();
        $minDays = (int) floor($minDate->getTimestamp() / 86400);
        $maxDays = (int) floor($maxDate->getTimestamp() / 86400);
        $tags = $generator->generateRangeQueryTags((float) $minDays, (float) $maxDays);

        if (empty($tags)) {
            return [];
        }

        return $this->queryByTags($tags);
    }

    // --- Private helpers ---

    /**
     * Query by multiple tags using PostgreSQL array overlap operator.
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

        $ids = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT id FROM user_queryable WHERE string_to_array(safecontent, \',\') && CAST(:tags AS text[]) ORDER BY id ASC',
            ['tags' => $this->toPostgresTextArrayLiteral($encodedTags)]
        )->fetchFirstColumn();

        if ($ids === []) {
            return [];
        }

        $entities = $this->findBy(['id' => $ids]);
        $byId = [];
        foreach ($entities as $entity) {
            $byId[(string) $entity->id] = $entity;
        }

        return array_values(array_filter(array_map(
            static fn(string|int $id): ?UserQueryable => $byId[(string) $id] ?? null,
            $ids
        )));
    }

    /**
     * Query by multiple tags using PostgreSQL array overlap operator, return single result.
     *
     * @param string[] $tags Binary tags (not yet base64 encoded)
     */
    private function queryByTagsOne(array $tags): ?UserQueryable
    {
        $encodedTags = array_map(static fn(string $t): string => base64_encode($t), $tags);

        if ($encodedTags === []) {
            return null;
        }

        $id = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT id FROM user_queryable WHERE string_to_array(safecontent, \',\') && CAST(:tags AS text[]) ORDER BY id ASC LIMIT 1',
            ['tags' => $this->toPostgresTextArrayLiteral($encodedTags)]
        )->fetchOne();

        if ($id === false) {
            return null;
        }

        return $this->find((int) $id);
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

