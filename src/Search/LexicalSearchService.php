<?php

declare(strict_types=1);

namespace App\Search;

use App\Document\Chunk;
use App\Document\SourceType;
use Doctrine\ODM\MongoDB\DocumentManager;

/**
 * Classic Atlas Search (Lucene) full-text query on the same "content" field and the same
 * corpus as VectorSearchService, so lexical and semantic search can be compared head to
 * head instead of only against an entirely different system (GitHub Search).
 */
final class LexicalSearchService
{
    private const INDEX_NAME = 'chunks_lucene_idx';

    public function __construct(private readonly DocumentManager $dm)
    {
    }

    /** @return list<SearchResult> */
    public function search(string $query, ?SourceType $sourceType = null, int $limit = 10): array
    {
        $builder = $this->dm->createAggregationBuilder(Chunk::class);
        $search = $builder->search()->index(self::INDEX_NAME);

        if ($sourceType !== null) {
            $compound = $search->compound();
            $compound->must()->text()->query($query)->path('content');
            $compound->filter()->equals('sourceType', $sourceType->value);
        } else {
            $search->text()->query($query)->path('content');
        }

        $builder->limit($limit);
        $builder->project()
            ->includeFields(['content', 'sourceType', 'parentId', 'metadata'])
            ->field('score')->meta('searchScore');

        $results = [];
        foreach ($builder->execute() as $document) {
            $results[] = SearchResult::fromArray((array) $document);
        }

        return $results;
    }
}
