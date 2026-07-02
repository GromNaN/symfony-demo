<?php

declare(strict_types=1);

namespace App\Document;

enum SourceType: string
{
    case Issue = 'issue';
    case PullRequest = 'pull_request';
    case Comment = 'comment';
    case DocPage = 'doc_page';
    case CodeFile = 'code_file';
}
