<?php

declare(strict_types=1);

namespace App\Search;

use App\Document\Chunk;
use App\Document\SourceType;
use Doctrine\ODM\MongoDB\DocumentManager;

/**
 * Single entry point for the $vectorSearch query, reused by both the web search
 * controller and the MCP tools — no query logic is duplicated between them.
 */
final class VectorSearchService
{
    private const INDEX_NAME = 'chunks_vector_idx';

    public function __construct(private readonly DocumentManager $dm)
    {
    }

    /** @return list<SearchResult> */
    public function search(string $query, ?SourceType $sourceType = null, string $model = 'voyage-4-lite', int $limit = 10): array
    {
        $builder = $this->dm->createAggregationBuilder(Chunk::class);

        $vectorSearch = $builder->vectorSearch()
            ->index(self::INDEX_NAME)
            ->path('content')
            ->query($query)
            ->model($model)
            ->numCandidates($limit * 10)
            ->limit($limit);

        if ($sourceType !== null) {
            $vectorSearch->filter(['sourceType' => $sourceType->value]);
        }

        $builder->project()
            ->includeFields(['content', 'sourceType', 'parentId', 'metadata'])
            ->field('score')->meta('vectorSearchScore');

        $results = [];
        foreach ($builder->execute() as $document) {
            $results[] = SearchResult::fromArray((array) $document);
        }

        return $results;
    }
}
