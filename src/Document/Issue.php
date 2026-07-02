<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Raw GitHub issue, kept as-is (no embedding). Used to render enriched search results
 * and to detect content changes (via $contentHash) so ingestion can skip re-chunking
 * and re-embedding unchanged issues on every run.
 */
#[ODM\Document(collection: 'issues')]
class Issue
{
    #[ODM\Id(strategy: 'none', type: 'string')]
    public string $id;

    #[ODM\Field(type: 'int')]
    public int $number;

    #[ODM\Field(type: 'string')]
    public string $title;

    #[ODM\Field(type: 'string')]
    public string $body;

    #[ODM\Field(type: 'string')]
    public string $state;

    #[ODM\Field(type: 'string')]
    public string $url;

    #[ODM\Field(type: 'string')]
    public string $author;

    /** @var list<string> */
    #[ODM\Field(type: 'collection')]
    public array $labels;

    #[ODM\Field(type: 'date_immutable')]
    public \DateTimeImmutable $updatedAt;

    #[ODM\Field(type: 'string')]
    public string $contentHash;

    /** @param list<string> $labels */
    public function __construct(
        int $number,
        string $title,
        string $body,
        string $state,
        string $url,
        string $author,
        array $labels,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id = 'issue-' . $number;
        $this->number = $number;
        $this->title = $title;
        $this->body = $body;
        $this->state = $state;
        $this->url = $url;
        $this->author = $author;
        $this->labels = $labels;
        $this->updatedAt = $updatedAt;
        $this->contentHash = hash('xxh3', $title . "\n" . $body);
    }
}
