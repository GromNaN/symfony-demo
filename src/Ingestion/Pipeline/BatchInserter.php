<?php

declare(strict_types=1);

namespace App\Ingestion\Pipeline;

use Doctrine\ODM\MongoDB\DocumentManager;

/**
 * Thin batching wrapper around DocumentManager::flush(). Documents with an assigned
 * identifier (our Chunk/Issue/... ids, all strategy "none") are upserted by Doctrine
 * MongoDB ODM automatically, so persist()+flush() is already idempotent — no need to
 * hand-roll bulkWrite/ReplaceOne here.
 */
final class BatchInserter
{
    private int $pending = 0;

    public function __construct(
        private readonly DocumentManager $dm,
        private readonly int $batchSize = 150,
    ) {
    }

    public function add(object $document): void
    {
        $this->dm->persist($document);

        if (++$this->pending >= $this->batchSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->pending === 0) {
            return;
        }

        $this->dm->flush();
        $this->pending = 0;
    }
}
