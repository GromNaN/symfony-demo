<?php

namespace App\MongoDB\Document;

use MongoDB\BSON\ObjectId;

class Post
{
    public function __construct(
        public string $title,
        public string $slug,
        public string $summary,
        public string $content,
        public ?\DateTimeInterface $publishedAt,
        public User $author,
        /** @var list<Tag> */
        public array $tags = [],
        public array $comments = [],
        public readonly ObjectId $id = new ObjectId(),
    ) {
    }
}
