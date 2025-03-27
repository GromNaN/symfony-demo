<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ODM\EmbeddedDocument]
class Step
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field]
    public int $stepNumber;

    #[Groups(['recipe:read', 'recipe:write'])]
    #[ODM\Field]
    public string $description;
}
