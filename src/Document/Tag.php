<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Types\Type;
/**
 * Defines the properties of the Tag document to represent the post tags.
 */
#[ODM\EmbeddedDocument]
class Tag implements \JsonSerializable
{
    #[ODM\Field(type: Type::STRING)]
    // With embedded documents, we don't need the unique index because the data
    // is duplicated in each document
    //#[ODM\UniqueIndex(keys: ['name' => 'asc'], options: ['unique' => true])]
    private readonly string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function jsonSerialize(): string
    {
        // This entity implements JsonSerializable (http://php.net/manual/en/class.jsonserializable.php)
        // so this method is used to customize its JSON representation when json_encode()
        // is called, for example in tags|json_encode (templates/form/fields.html.twig)

        return $this->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
