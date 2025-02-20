<?php

namespace App\Document;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Validator\Constraints as Assert;

#[ODM\Document]
#[ApiResource(types: ['https://schema.org/Offer'])]
class Offer
{
    #[ODM\Id(strategy: 'INCREMENT', type: 'int')]
    private int $id;

    #[ODM\Field]
    public string $description;

    #[ODM\Field(type: 'float')]
    #[Assert\Range(min: 0, minMessage: 'The price must be superior to 0.')]
    #[Assert\Type(type: 'float')]
    public float $price;

    #[ODM\ReferenceOne(targetDocument: Product::class, inversedBy: 'offers', storeAs: 'id')]
    public ?Product $product;

    public function getId(): ?int
    {
        return $this->id;
    }
}
