<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\EmbeddedDocument]
#[ODM\Encrypt]
class ClientCard
{
    public function __construct(
        #[ODM\Field]
        public string $type,
        #[ODM\Field]
        public string $number,
    ) {
    }
}
