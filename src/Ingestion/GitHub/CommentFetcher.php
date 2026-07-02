<?php

declare(strict_types=1);

namespace App\Ingestion\GitHub;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/** General (non-review) comments — GitHub exposes them the same way for both issues and pull requests. */
final class CommentFetcher
{
    public function __construct(
        private readonly HttpClientInterface $githubClient,
        private readonly string $githubRepo,
        private readonly RateLimitGuard $rateLimitGuard,
    ) {
    }

    /** @return iterable<array<string, mixed>> */
    public function fetchForIssue(int $number): iterable
    {
        $page = 1;

        do {
            $response = $this->githubClient->request('GET', \sprintf('/repos/%s/issues/%d/comments', $this->githubRepo, $number), [
                'query' => ['per_page' => 100, 'page' => $page],
            ]);
            $items = $response->toArray();
            $this->rateLimitGuard->throttle($response);

            yield from $items;

            ++$page;
        } while (\count($items) === 100);
    }
}
