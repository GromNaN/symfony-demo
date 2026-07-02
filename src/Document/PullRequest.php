<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'pull_requests')]
class PullRequest
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

    #[ODM\Field(type: 'bool')]
    public bool $merged;

    #[ODM\Field(type: 'date_immutable')]
    public \DateTimeImmutable $updatedAt;

    #[ODM\Field(type: 'string')]
    public string $contentHash;

    public function __construct(
        int $number,
        string $title,
        string $body,
        string $state,
        string $url,
        string $author,
        bool $merged,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id = 'pr-' . $number;
        $this->number = $number;
        $this->title = $title;
        $this->body = $body;
        $this->state = $state;
        $this->url = $url;
        $this->author = $author;
        $this->merged = $merged;
        $this->updatedAt = $updatedAt;
        $this->contentHash = hash('xxh3', $title . "\n" . $body);
    }
}
