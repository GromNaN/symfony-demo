<?php

namespace App\Codec;

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
 * @implements ProviderInterface<CodecPlane>
 * @implements ProcessorInterface<CodecPlane>
 */
class CodecState implements ProcessorInterface, ProviderInterface
{
    public function __construct(
        // The collection must be configured with the codec
        #[AutowireCollection(collection: 'planes', codec: PlaneCodec::class)]
        private Collection $collection,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof CodecPlane) {
            return;
        }

        $bson = $this->collection->getCodec()->encode($data);

        if ($operation instanceof Post) {
            $this->collection->insertOne($bson, ['codec' => null]);
        }

        if ($operation instanceof Put || $operation instanceof Patch) {
            $this->collection->replaceOne(['_id' => $bson->get('_id')], $bson, ['codec' => null]);
        }

        if ($operation instanceof Delete) {
            $this->collection->deleteOne(['_id' => $bson->get('_id')]);
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
                // Validate the ObjectId is lowercast. Or use Requirement::MONGODB_ID
                $objectId = new ObjectId($uriVariables['id']);
            } catch (\Exception) {
                return null;
            }

            $cursor = $this->collection->aggregate([
                ['$match' => ['_id' => $objectId]], // @todo match the request ID
                ['$limit' => 1],
            ]);

            return $cursor->toArray()[0] ?? null;
        }
    }
}
