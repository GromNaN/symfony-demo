<?php

namespace App\MongoDB;

use MongoDB\BSON\ObjectId;
use MongoDB\Bundle\Attribute\AutowireDatabase;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\CursorInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

class Repository
{
    private array $collections;

    public function __construct(
        #[AutowireDatabase('blog')]
        private readonly Database $defaultDatabase,
        #[AutowireLocator('mongodb.document_codec')]
        private readonly ContainerInterface $codecs
    ) {

    }

    /**
     * @template T
     * @param class-string<T> $modelClass
     * @return T|null
     */
    public function find(string $modelClass, ObjectId|string|int $id): ?object
    {
        return $this->getCollection($modelClass)->findOne(['_id' => $id]);
    }

    /**
     * @template T
     * @param class-string<T> $modelClass
     * @param array $filter
     * @param array $options
     * @return T|null
     */
    public function findOne(string $modelClass, array $filter = [], array $options = []): ?object
    {
        return $this->getCollection($modelClass)->findOne($filter, $options);
    }

    /**
     * @template T
     * @param class-string<T> $modelClass
     * @param array $filter
     * @param array $options
     * @return CursorInterface&iterable<T>
     */
    public function findMany(string $modelClass, array $filter = [], array $options = []): iterable
    {
        return $this->getCollection($modelClass)->find($filter, $options);
    }

    public function insert(object $document): void
    {
        $this->getCollection($document::class)->insertOne($document);
    }

    public function replace(object $document): void
    {
        $this->getCollection($document::class)->replaceOne(['_id' => $document->id], $document);
    }

    public function delete(object $document): void
    {
        $this->getCollection($document::class)->deleteOne(['_id' => $document->id]);
    }

    public function aggregate(string $modelClass, array $pipeline, array $options = []): CursorInterface
    {
        return $this->getCollection($modelClass)->aggregate($pipeline, ['codec' => $this->codecs->get($modelClass)] + $options);
    }

    public function getCollection(string $modelClass): Collection
    {
        // @todo support custom collection names
        $collection = strtolower((new \ReflectionClass($modelClass))->getShortName()).'s';

        return $this->collections[$modelClass] ??= $this->defaultDatabase->selectCollection($collection, [
            'codec' => $this->codecs->get($modelClass),
        ]);
    }
}
