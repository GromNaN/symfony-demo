<?php

declare(strict_types=1);

namespace App\Ingestion\GitHub;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class IssueFetcher
{
    public function __construct(
        private readonly HttpClientInterface $githubClient,
        private readonly string $githubRepo,
        private readonly RateLimitGuard $rateLimitGuard,
    ) {
    }

    /** @return iterable<array<string, mixed>> raw GitHub issue payloads (pull requests excluded) */
    public function fetchAll(): iterable
    {
        $page = 1;

        do {
            $response = $this->githubClient->request('GET', \sprintf('/repos/%s/issues', $this->githubRepo), [
                'query' => ['state' => 'all', 'per_page' => 100, 'page' => $page],
            ]);
            $items = $response->toArray();
            $this->rateLimitGuard->throttle($response);

            foreach ($items as $item) {
                // GitHub's /issues endpoint also returns pull requests; PullRequestFetcher owns those.
                if (isset($item['pull_request'])) {
                    continue;
                }

                yield $item;
            }

            ++$page;
        } while (\count($items) === 100);
    }
}
