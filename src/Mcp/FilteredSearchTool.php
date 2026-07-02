<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Document\SourceType;
use App\Search\VectorSearchService;
use Mcp\Capability\Attribute\McpTool;

#[McpTool(
    name: 'search_knowledge_base_by_type',
    description: 'Semantic search restricted to one source type: issue, pull_request, comment, doc_page or code_file.',
)]
final class FilteredSearchTool
{
    public function __construct(private readonly VectorSearchService $searchService)
    {
    }

    /**
     * @return list<array{content: string, sourceType: string, url: ?string, score: float}>
     */
    public function __invoke(string $query, string $sourceType, int $limit = 5): array
    {
        $type = SourceType::from($sourceType);

        return array_map(
            static fn ($result) => [
                'content' => $result->content,
                'sourceType' => $result->sourceType->value,
                'url' => $result->metadata['url'] ?? null,
                'score' => $result->score,
            ],
            $this->searchService->search($query, $type, limit: $limit),
        );
    }
}
