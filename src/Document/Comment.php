<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/** A comment on an Issue or PullRequest. $parentId points to an Issue or PullRequest document id. */
#[ODM\Document(collection: 'comments')]
class Comment
{
    #[ODM\Id(strategy: 'none', type: 'string')]
    public string $id;

    #[ODM\Field(type: 'string')]
    public string $parentId;

    #[ODM\Field(type: 'string')]
    public string $body;

    #[ODM\Field(type: 'string')]
    public string $author;

    #[ODM\Field(type: 'string')]
    public string $url;

    #[ODM\Field(type: 'date_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ODM\Field(type: 'string')]
    public string $contentHash;

    public function __construct(
        int $githubCommentId,
        string $parentId,
        string $body,
        string $author,
        string $url,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = 'comment-' . $githubCommentId;
        $this->parentId = $parentId;
        $this->body = $body;
        $this->author = $author;
        $this->url = $url;
        $this->createdAt = $createdAt;
        $this->contentHash = hash('xxh3', $body);
    }
}
