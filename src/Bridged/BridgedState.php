<?php

namespace App\Bridged;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Doctrine\Common\State\RemoveProcessor;
use ApiPlatform\Doctrine\Odm\State\CollectionProvider;
use ApiPlatform\Doctrine\Odm\State\ItemProvider;
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
use App\Document\Plane;
use AutoMapper\AutoMapperInterface;
use Symfony\Component\HttpFoundation\Request;

class BridgedState implements ProcessorInterface, ProviderInterface
{
    public function __construct(
        private AutoMapperInterface $autoMapper,
        private CollectionProvider $collectionProvider,
        private ItemProvider $itemProvider,
        private PersistProcessor $persistProcessor,
        private RemoveProcessor $removeProcessor,
    ) {
    }

    /** @param array{request: Request, ...} $context */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data instanceof BridgedPlane) {
            return;
        }

        $targetToPopulate = $context['request']->attributes->get('doctrine_data');
        $data = $this->autoMapper->map($data, Plane::class, ['target_to_populate' => $targetToPopulate]);

        if ($operation instanceof Delete) {
            $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        } elseif ($operation instanceof Patch || $operation instanceof Put || $operation instanceof Post) {
            $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }
    }

    /** @param array{request: Request, ...} $context */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            $results = $this->collectionProvider->provide($operation, $uriVariables, $context);
            assert($results instanceof PaginatorInterface);

            $mapper = $this->autoMapper->getMapper(Plane::class, BridgedPlane::class);

            return new TraversablePaginator(
                new \ArrayIterator($this->autoMapper->mapCollection($results, BridgedPlane::class)),
                $results->getCurrentPage(), $results->getItemsPerPage(), $results->getTotalItems()
            );
        }

        if ($operation instanceof Get || $operation instanceof Patch || $operation instanceof Delete) {
            $item = $this->itemProvider->provide($operation, $uriVariables, $context);

            if (null === $item) {
                return null;
            }
            $context['request']->attributes->set('doctrine_data', $item);

            return $this->autoMapper->map($item, BridgedPlane::class);
        }
    }
}
