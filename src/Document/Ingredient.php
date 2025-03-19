<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Validator\Constraints as Assert;

#[ODM\EmbeddedDocument]
class Ingredient
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field]
    #[Assert\NotBlank]
    public string $name;

    #[ODM\Field]
    #[Assert\Positive]
    public float $quantity;

    #[ODM\Field]
    #[Assert\NotBlank]
    public string $unit;
}
