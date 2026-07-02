<?php

declare(strict_types=1);

namespace App\Search;

use App\Document\SourceType;

final class SearchResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $content,
        public readonly SourceType $sourceType,
        public readonly string $parentId,
        public readonly array $metadata,
        public readonly float $score,
    ) {
    }

    /** @param array<string, mixed> $document */
    public static function fromArray(array $document): self
    {
        return new self(
            content: (string) $document['content'],
            sourceType: SourceType::from((string) $document['sourceType']),
            parentId: (string) $document['parentId'],
            metadata: (array) ($document['metadata'] ?? []),
            score: (float) ($document['score'] ?? 0.0),
        );
    }
}
