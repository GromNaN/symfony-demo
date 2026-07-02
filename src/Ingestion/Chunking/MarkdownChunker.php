<?php

declare(strict_types=1);

namespace App\Ingestion\Chunking;

/**
 * Chunks GitHub-flavored Markdown (issue/PR/comment bodies) using symfony/ai-store's
 * TextSplitTransformer. Trade-off accepted deliberately: this is a blind character
 * window, so it can occasionally cut through a paragraph or a fenced code block —
 * we favor reusing the framework's own splitting tool over a hand-rolled semantic
 * splitter, and document the trade-off rather than hide it.
 */
final class MarkdownChunker implements ChunkerInterface
{
    public function __construct(private readonly ChunkSplitter $splitter)
    {
    }

    public function chunk(string $content, array $context = []): array
    {
        return $this->splitter->split($content, $context);
    }
}
