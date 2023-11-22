<?php

namespace App\MongoDB\Document;

use MongoDB\BSON\ObjectId;

class Tag
{
    public function __construct(
        public string $name,
        public readonly ObjectId $id = new ObjectId(),
    ) {
    }
}
