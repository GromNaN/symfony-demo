<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/** One .rst file from the repository's doc/ folder, used as a wiki substitute (no GitHub wiki is enabled on EasyAdminBundle). */
#[ODM\Document(collection: 'doc_pages')]
class DocPage
{
    #[ODM\Id(strategy: 'none', type: 'string')]
    public string $id;

    #[ODM\Field(type: 'string')]
    public string $path;

    #[ODM\Field(type: 'string')]
    public string $title;

    #[ODM\Field(type: 'string')]
    public string $rawContent;

    #[ODM\Field(type: 'string')]
    public string $url;

    #[ODM\Field(type: 'string')]
    public string $contentHash;

    public function __construct(string $path, string $title, string $rawContent, string $url)
    {
        $this->id = 'doc-' . hash('xxh3', $path);
        $this->path = $path;
        $this->title = $title;
        $this->rawContent = $rawContent;
        $this->url = $url;
        $this->contentHash = hash('xxh3', $rawContent);
    }
}
