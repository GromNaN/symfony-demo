<?php

namespace App\MongoDB\Document;

use MongoDB\BSON\ObjectId;

class Comment
{
    public function __construct(
        public string $content,
        public ?User $author = null,
        public ?Post $post = null,
        public \DateTimeImmutable $publishedAt = new \DateTimeImmutable(),
        public readonly ObjectId $id = new ObjectId(),
    ) {
    }
}
