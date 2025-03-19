<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\EmbeddedDocument]
class Popularity
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field]
    public float $averageRating;

    #[ODM\Field]
    public int $numberOfVotes;
}
