<?php

declare(strict_types=1);

namespace App\Eval;

use App\Search\SearchResult;

final class ComparisonRow
{
    /**
     * @param array{title: string, url: string, snippet: ?string}|null $githubIssueTop
     * @param array{title: string, url: string, snippet: ?string}|null $githubCodeTop
     */
    public function __construct(
        public readonly string $query,
        public readonly string $kind,
        public readonly ?SearchResult $vectorTop,
        public readonly ?array $githubIssueTop,
        public readonly ?array $githubCodeTop,
        public readonly float $vectorLatencyMs,
        public readonly float $githubLatencyMs,
    ) {
    }
}
