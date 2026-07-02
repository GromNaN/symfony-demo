<?php

declare(strict_types=1);

namespace App\Ingestion\Pipeline;

use App\Document\Chunk;
use App\Document\CodeFile;
use App\Document\Comment;
use App\Document\DocPage;
use App\Document\Issue;
use App\Document\PullRequest;
use App\Document\SourceType;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\AI\Store\Document\TextDocument;

/**
 * Persists a business document (Issue, PullRequest, Comment, DocPage, CodeFile) and,
 * unless its content is unchanged since the last run, (re)chunks it into Chunk documents.
 * Skipping unchanged content avoids paying for re-embedding on every ingestion run.
 */
final class IngestionPipeline
{
    public function __construct(
        private readonly DocumentManager $dm,
        private readonly BatchInserter $batchInserter,
    ) {
    }

    /**
     * @param Issue|PullRequest|Comment|DocPage|CodeFile $businessDocument
     * @param iterable<TextDocument>                      $chunks
     *
     * @return int Number of chunks (re)written; 0 means content was unchanged and was skipped.
     */
    public function ingest(
        Issue|PullRequest|Comment|DocPage|CodeFile $businessDocument,
        SourceType $sourceType,
        iterable $chunks,
    ): int {
        $repository = $this->dm->getRepository($businessDocument::class);
        $existing = $repository->find($businessDocument->id);
        $unchanged = $existing !== null && $existing->contentHash === $businessDocument->contentHash;

        $this->batchInserter->add($businessDocument);

        if ($unchanged) {
            return 0;
        }

        $parentId = $businessDocument->id;
        $count = 0;

        foreach ($chunks as $index => $textDocument) {
            $id = ChunkIdHasher::id($sourceType, $parentId, $index, $textDocument->getContent());
            $chunk = new Chunk($id, $parentId, $sourceType, $textDocument->getContent(), $index, $textDocument->getMetadata()->getArrayCopy());
            $this->batchInserter->add($chunk);
            ++$count;
        }

        return $count;
    }

    public function flush(): void
    {
        $this->batchInserter->flush();
    }
}
