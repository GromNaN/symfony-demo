<?php

declare(strict_types=1);

namespace App\Ingestion\Pipeline;

use App\Document\SourceType;

/**
 * Deterministic chunk ids make re-ingestion idempotent: same content -> same _id -> upsert,
 * not a duplicate. xxh3 (not sha1/sha256): this is a content-addressing key, not a security
 * boundary, so we use the fastest well-distributed hash rather than a cryptographic one.
 */
final class ChunkIdHasher
{
    public static function id(SourceType $sourceType, string $parentId, int $chunkIndex, string $content): string
    {
        return hash('xxh3', \sprintf('%s:%s:%d:%s', $sourceType->value, $parentId, $chunkIndex, hash('xxh3', $content)));
    }
}
