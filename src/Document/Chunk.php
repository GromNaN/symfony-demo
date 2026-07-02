<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Mapping\Attribute\SearchIndex;
use Doctrine\ODM\MongoDB\Mapping\Attribute\VectorSearchIndex;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;

/**
 * A single semantically-chunked piece of content extracted from an Issue, PullRequest,
 * Comment, DocPage or CodeFile. This is the only document carrying the autoEmbed field:
 * MongoDB Atlas does not support arrays of embeddings, so "one chunk = one document"
 * (referencing its parent via $parentId) rather than an embedded array on the parent.
 *
 * Carries two independent indexes on the same "content" field: the autoEmbed vector
 * index (semantic search) and a classic Lucene full-text index (lexical search), so the
 * two can be compared on the exact same corpus rather than against GitHub Search.
 */
#[ODM\Document(collection: 'chunks')]
#[VectorSearchIndex(
    name: 'chunks_vector_idx',
    fields: [
        [
            'type' => 'autoEmbed',
            'path' => 'content',
            'modality' => ClassMetadata::VECTOR_AUTOEMBEDDING_MODALITY_TEXT,
            'model' => 'voyage-4',
            'similarity' => ClassMetadata::VECTOR_SIMILARITY_DOT_PRODUCT,
        ],
        ['type' => 'filter', 'path' => 'sourceType'],
        ['type' => 'filter', 'path' => 'parentId'],
    ],
)]
#[SearchIndex(
    name: 'chunks_lucene_idx',
    fields: [
        'content' => ['type' => 'string'],
        'sourceType' => ['type' => 'token'],
        'parentId' => ['type' => 'token'],
    ],
)]
class Chunk
{
    #[ODM\Id(strategy: 'none', type: 'string')]
    public string $id;

    #[ODM\Field(type: 'string')]
    public string $parentId;

    #[ODM\Field(type: 'string', enumType: SourceType::class)]
    public SourceType $sourceType;

    /** Text embedded automatically by MongoDB Atlas via the autoEmbed index field above. */
    #[ODM\Field(type: 'string')]
    public string $content;

    #[ODM\Field(type: 'int')]
    public int $chunkIndex;

    /** @var array<string, mixed> */
    #[ODM\Field(type: 'hash')]
    public array $metadata;

    #[ODM\Field(type: 'date_immutable')]
    public \DateTimeImmutable $indexedAt;

    /** @param array<string, mixed> $metadata */
    public function __construct(
        string $id,
        string $parentId,
        SourceType $sourceType,
        string $content,
        int $chunkIndex,
        array $metadata,
    ) {
        $this->id = $id;
        $this->parentId = $parentId;
        $this->sourceType = $sourceType;
        $this->content = $content;
        $this->chunkIndex = $chunkIndex;
        $this->metadata = $metadata;
        $this->indexedAt = new \DateTimeImmutable();
    }
}
