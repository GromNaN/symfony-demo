<?php

namespace App\Automapper;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Bson\FilterInterface;
use AutoMapper\AutoMapper;
use MongoDB\BSON\ObjectId;
use MongoDB\Bundle\Attribute\AutowireDatabase;
use MongoDB\Collection;
use MongoDB\Database;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ServiceProviderInterface;

readonly class State implements ProcessorInterface, ProviderInterface
{
    public function __construct(
        #[AutowireDatabase(typeMap: ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])]
        private Database $database,
        #[Autowire(service: 'api_platform.filter_locator')]
        private ServiceProviderInterface $filters,
        private AutoMapper $autoMapper,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $resourceClass = $operation->getClass();
        if (!$data instanceof $resourceClass) {
            throw new \LogicException(sprintf('The data must be an instance of "%s".', $context['resource_class']));
        }

        if ($operation instanceof Post) {
            $data->id ??= (string) new ObjectId();
            $document = $this->autoMapper->map($data, 'array');
            $this->getCollection($operation)->insertOne($document);
        }

        if ($operation instanceof Put || $operation instanceof Patch) {
            $document = $this->autoMapper->map($data, 'array');
            $this->getCollection($operation)->replaceOne(['_id' => new ObjectId($data->id)], $document);
        }

        if ($operation instanceof Delete) {
            $this->getCollection($operation)->deleteOne(['_id' => new ObjectId($data->id)]);
        }

        return $data;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();

        if ($operation instanceof CollectionOperationInterface) {
            $pipeline = [];
            foreach ($operation->getFilters() as $filterId) {
                $filter = $this->filters->get($filterId);
                assert($filter instanceof FilterInterface, new \LogicException(sprintf('The filter "%s" must implement "%s".', $filter::class, FilterInterface::class)));
                $pipeline = $filter->apply($pipeline, $context);
            }

            $results = $this->getCollection($operation)->aggregate($pipeline)->toArray();

            $results = $this->autoMapper->mapCollection($results, $resourceClass);

            return $results;
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

            $results = $this->getCollection($operation)->aggregate($pipeline)->toArray();

            $results = $this->autoMapper->mapCollection($results, $resourceClass);

            return $results[0] ?? null;
        }
    }

    private function getCollection(Operation $operation): Collection
    {
        $collectionName = strtolower((new \ReflectionClass($operation->getClass()))->getShortName().'s');

        return $this->database->getCollection($collectionName);
    }
}
