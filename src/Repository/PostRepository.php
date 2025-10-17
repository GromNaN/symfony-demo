<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Document\Post;
use App\Document\Tag;
use App\Pagination\Paginator;
use Doctrine\Bundle\MongoDBBundle\Repository\ServiceDocumentRepository;
use Doctrine\Persistence\ManagerRegistry;

use function Symfony\Component\String\u;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine.html#querying-for-objects-the-repository
 *
 * @method Post|null findOneByTitle(string $postTitle)
 *
 * @template-extends ServiceDocumentRepository<Post>
 */
class PostRepository extends ServiceDocumentRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function findLatest(int $page = 1, ?string $tag = null): Paginator
    {
        $qb = $this->createQueryBuilder()
            ->field('publishedAt')->lte(new \DateTimeImmutable()) // $$NOW is a MongoDB variable that returns the current date
            ->sort('publishedAt', 'DESC');

        if ($tag) {
            $qb->field('tags.name')->equals($tag);
        }

        return (new Paginator($qb))->paginate($page);
    }

    /**
     * @return Post[]
     */
    public function findBySearchQuery(string $query, int $limit = Paginator::PAGE_SIZE): array
    {
        if (!$query) {
            return [];
        }

        /** @var Post[] $result */
        $result = $this->findBy(
            ['$text' => ['$search' => $query]],
            ['publishedAt' => -1],
            $limit
        );

        return $result;
    }

    /**
     * Transforms the search string into an array of search terms.
     *
     * @return string[]
     */
    public function findAllTags(): array
    {
        $result = $this->createAggregationBuilder()
            ->unwind('$tags')
            ->sortByCount('$tags.name')
            ->project()->includeFields(['_id'])
            ->getAggregation()
            ->getIterator()
            ->toArray();

        return array_column($result, '_id');
    }
}
