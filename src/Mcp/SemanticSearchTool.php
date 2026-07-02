<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Search\VectorSearchService;
use Mcp\Capability\Attribute\McpTool;

#[McpTool(
    name: 'search_knowledge_base',
    description: 'Semantic search across EasyAdminBundle issues, pull requests, comments, documentation and source code.',
)]
final class SemanticSearchTool
{
    public function __construct(private readonly VectorSearchService $searchService)
    {
    }

    /**
     * @return list<array{content: string, sourceType: string, url: ?string, score: float}>
     */
    public function __invoke(string $query, int $limit = 5): array
    {
        return array_map(
            static fn ($result) => [
                'content' => $result->content,
                'sourceType' => $result->sourceType->value,
                'url' => $result->metadata['url'] ?? null,
                'score' => $result->score,
            ],
            $this->searchService->search($query, limit: $limit),
        );
    }
}
