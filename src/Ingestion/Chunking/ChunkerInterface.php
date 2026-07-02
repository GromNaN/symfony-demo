<?php

declare(strict_types=1);

namespace App\Ingestion\Chunking;

use Symfony\AI\Store\Document\TextDocument;

interface ChunkerInterface
{
    /**
     * @param array<string, mixed> $context extra metadata merged into every produced chunk
     *
     * @return list<TextDocument>
     */
    public function chunk(string $content, array $context = []): array;
}
