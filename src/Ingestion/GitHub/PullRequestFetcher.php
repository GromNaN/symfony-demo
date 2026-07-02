<?php

declare(strict_types=1);

namespace App\Ingestion\GitHub;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PullRequestFetcher
{
    public function __construct(
        private readonly HttpClientInterface $githubClient,
        private readonly string $githubRepo,
        private readonly RateLimitGuard $rateLimitGuard,
    ) {
    }

    /** @return iterable<array<string, mixed>> raw GitHub pull request payloads */
    public function fetchAll(): iterable
    {
        $page = 1;

        do {
            $response = $this->githubClient->request('GET', \sprintf('/repos/%s/pulls', $this->githubRepo), [
                'query' => ['state' => 'all', 'per_page' => 100, 'page' => $page],
            ]);
            $items = $response->toArray();
            $this->rateLimitGuard->throttle($response);

            yield from $items;

            ++$page;
        } while (\count($items) === 100);
    }
}
