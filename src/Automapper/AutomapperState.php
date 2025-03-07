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
use MongoDB\BSON\ObjectId;
use MongoDB\Bundle\Attribute\AutowireCollection;
use MongoDB\Collection;

/**
 * @implements ProviderInterface<AutomapperPlane>
 * @implements ProcessorInterface<AutomapperPlane>
 */
class AutomapperState implements ProcessorInterface, ProviderInterface
{
    public function __construct(
        // The collection must be configured with the codec
        #[AutowireCollection(collection: 'planes', codec: AutomapperCodec::class)]
        private Collection $collection,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data instanceof AutomapperPlane) {
            return;
        }

        if ($operation instanceof Post) {
            $data->id ??= new ObjectId();
            $this->collection->insertOne($data);
        }

        if ($operation instanceof Put || $operation instanceof Patch) {
            $this->collection->replaceOne(['_id' => new ObjectId($data->id)], $data);
        }

        if ($operation instanceof Delete) {
            $this->collection->deleteOne(['_id' => new ObjectId($data->id)]);
        }
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $cursor = $this->collection->aggregate([
                ['$match' => new \stdClass()],
            ]);

            return $cursor->toArray();
        }

        if ($operation instanceof Get || $operation instanceof Patch || $operation instanceof Delete) {
            assert(isset($uriVariables['id']));
            try {
                $objectId = new ObjectId($uriVariables['id']);
            } catch (\Exception) {
                // @todo throw a 404 exception
            }

            $cursor = $this->collection->aggregate([
                ['$match' => ['_id' => $objectId]], // @todo match the request ID
                ['$limit' => 1],
            ]);

            return $cursor->toArray()[0] ?? null;
        }
    }
}
