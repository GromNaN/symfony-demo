<?php

declare(strict_types=1);

namespace App\Ingestion\Chunking;

use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;

/**
 * Thin wrapper around symfony/ai-store's TextSplitTransformer, shared by every chunker so
 * the actual "cut into size-limited pieces with overlap" logic lives in one place, provided
 * by the framework rather than hand-rolled here. Chunk size is in characters (the
 * transformer works on mb_strlen, not tokens); ~1500 chars / ~225 chars overlap (15%)
 * approximates the ~350 token / 15-20% overlap target from MongoDB/Voyage AI guidance.
 */
final class ChunkSplitter
{
    private const CHUNK_SIZE = 1500;
    private const OVERLAP = 225;

    private readonly TextSplitTransformer $transformer;

    public function __construct()
    {
        $this->transformer = new TextSplitTransformer(self::CHUNK_SIZE, self::OVERLAP);
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return list<TextDocument>
     */
    public function split(string $content, array $metadata = []): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $document = new TextDocument(bin2hex(random_bytes(8)), $content, new Metadata($metadata));

        return iterator_to_array($this->transformer->transform([$document]), false);
    }
}
