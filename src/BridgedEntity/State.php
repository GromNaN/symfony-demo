<?php

namespace App\BridgedEntity;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Doctrine\Common\State\RemoveProcessor;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use AutoMapper\AutoMapperInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class State implements ProcessorInterface, ProviderInterface
{
    public function __construct(
        private AutoMapperInterface $autoMapper,
        private CollectionProvider $collectionProvider,
        private ItemProvider $itemProvider,
        private PersistProcessor $persistProcessor,
        private RemoveProcessor $removeProcessor,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @param array{request: Request, ...} $context */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $modelClass = $operation->getClass();
        $entityClass = $operation->getStateOptions()->getEntityClass();

        if (!$data instanceof $modelClass) {
            return $data;
        }

        // If the data has an ID, we need to get the managed object from doctrine
        if (isset($data->id)) {
            $targetToPopulate = $this->entityManager->getReference($entityClass, $data->id);
        } else {
            $targetToPopulate = null;
        }

        $data = $this->autoMapper->map($data, $entityClass, ['target_to_populate' => $targetToPopulate]);

        if ($operation instanceof Delete) {
            $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        } elseif ($operation instanceof Patch || $operation instanceof Put || $operation instanceof Post) {
            $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        return $data;
    }

    /** @param array{request: Request, ...} $context */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $modelClass = $operation->getClass();

        if ($operation instanceof GetCollection) {
            $results = $this->collectionProvider->provide($operation, $uriVariables, $context);
            assert($results instanceof PaginatorInterface);

            return new TraversablePaginator(
                new \ArrayIterator($this->autoMapper->mapCollection($results, $modelClass)),
                $results->getCurrentPage(),
                $results->getItemsPerPage(),
                $results->getTotalItems(),
            );
        }

        if ($operation instanceof Get || $operation instanceof Patch || $operation instanceof Delete) {
            $item = $this->itemProvider->provide($operation, $uriVariables, $context);

            if (null === $item) {
                return null;
            }

            return $this->autoMapper->map($item, $modelClass);
            // Additional query to embed additional data
        }
    }
}
