<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/** One PHP file from the repository's src/ folder. */
#[ODM\Document(collection: 'code_files')]
class CodeFile
{
    #[ODM\Id(strategy: 'none', type: 'string')]
    public string $id;

    #[ODM\Field(type: 'string')]
    public string $path;

    #[ODM\Field(type: 'string')]
    public string $rawContent;

    #[ODM\Field(type: 'string')]
    public string $url;

    #[ODM\Field(type: 'string')]
    public string $contentHash;

    public function __construct(string $path, string $rawContent, string $url)
    {
        $this->id = 'code-' . hash('xxh3', $path);
        $this->path = $path;
        $this->rawContent = $rawContent;
        $this->url = $url;
        $this->contentHash = hash('xxh3', $rawContent);
    }
}
