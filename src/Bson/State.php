<?php

namespace App\Bson;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Serializable;
use MongoDB\BSON\Unserializable;
use MongoDB\Bundle\Attribute\AutowireDatabase;
use MongoDB\Collection;
use MongoDB\Database;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @implements ProviderInterface<Plane>
 * @implements ProcessorInterface<Plane>
 */
readonly class State implements ProcessorInterface, ProviderInterface
{
    public function __construct(
        #[AutowireDatabase]
        private Database $database,
        #[Autowire(service: 'api_platform.filter_locator')]
        private ServiceProviderInterface $filters,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data instanceof $context['resource_class']) {
            throw new \LogicException(sprintf('The data must be an instance of "%s".', $context['resource_class']));
        }

        if ($operation instanceof Post) {
            $data->id ??= new ObjectId();
            $this->getCollection($context)->insertOne($data);
        }

        if ($operation instanceof Put || $operation instanceof Patch) {
            $this->getCollection($context)->replaceOne(['_id' => new ObjectId($data->id)], $data);
        }

        if ($operation instanceof Delete) {
            $this->getCollection($context)->deleteOne(['_id' => new ObjectId($data->id)]);
        }

        return $data;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $context['resource_class'];
        if (!is_subclass_of($resourceClass, Serializable::class) && !is_subclass_of($resourceClass, Unserializable::class)) {
            throw new \LogicException(sprintf('The resource class "%s" must implement "%s" and "%s".', $resourceClass, Serializable::class, Unserializable::class));
        }

        if ($operation instanceof CollectionOperationInterface) {
            $pipeline = [];
            foreach ($operation->getFilters() as $filterId) {
                $filter = $this->filters->get($filterId);
                assert($filter instanceof FilterInterface, new \LogicException(sprintf('The filter "%s" must implement "%s".', $filter::class, FilterInterface::class)));
                $pipeline = $filter->apply($pipeline, $context);
            }

            return $this->getCollection($context)->aggregate(
                $pipeline,
                ['typeMap' => ['root' => $resourceClass]],
            )->toArray();
        }

        if ($operation instanceof Get || $operation instanceof Patch || $operation instanceof Delete) {
            assert(isset($uriVariables['id']));
            try {
                $objectId = new ObjectId($uriVariables['id']);
            } catch (\Exception) {
                // @todo throw a 404 exception
            }

            $pipeline = [
                ['$match' => ['_id' => $objectId]],
                ['$limit' => 1],
            ];

            $results = $this->getCollection($context)->aggregate(
                $pipeline,
                ['typeMap' => ['root' => $resourceClass]]
            )->toArray();

            return $results[0] ?? null;
        }
    }

    private function aggregate(array $context, array $pipeline): array
    {
        $options = ['typeMap' => ['root' => $context['resource_class']]];

        return $this->getCollection($context)->aggregate($pipeline, $options)->toArray();
    }

    private function getCollection(array $context): Collection
    {
        $collectionName = strtolower((new \ReflectionClass($context['resource_class']))->getShortName().'s');

        return $this->database->getCollection($collectionName);
    }
}
