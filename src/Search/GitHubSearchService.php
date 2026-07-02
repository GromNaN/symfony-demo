<?php

declare(strict_types=1);

namespace App\Search;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper around GitHub's /search/issues and /search/code REST endpoints, for
 * comparison against $vectorSearch.
 *
 * GitHub's search endpoints are also protected by an undocumented-in-headers secondary
 * ("abuse detection") rate limit, on top of the 30 req/min budget reported by
 * /rate_limit. In practice it can trigger well before that budget is exhausted (seen
 * consistently after ~19 requests in under a minute here, with `resources.search`
 * still reporting 30/30 remaining) and does not expose a `Retry-After` we could rely
 * on for an exact wait time. We self-throttle to space requests out and back off with
 * a fixed cooldown + a couple of retries on 403 rather than trying to compute an exact
 * wait time from headers that don't reflect this particular limit.
 */
final class GitHubSearchService
{
    private const MIN_INTERVAL_NANOS = 3_000_000_000;
    private const MAX_RETRIES = 3;
    private const RETRY_BACKOFF_SECONDS = 30;

    private int $lastRequestAt = 0;

    public function __construct(
        private readonly HttpClientInterface $githubClient,
        private readonly string $githubRepo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return list<array{title: string, url: string, snippet: ?string}> */
    public function searchIssues(string $query, int $limit = 5): array
    {
        $items = $this->search('/search/issues', $query, $limit);

        return array_map(static fn (array $item): array => [
            'title' => (string) $item['title'],
            'url' => (string) $item['html_url'],
            'snippet' => isset($item['body']) ? mb_substr(strip_tags((string) $item['body']), 0, 300) : null,
        ], $items);
    }

    /** @return list<array{title: string, url: string, snippet: ?string}> */
    public function searchCode(string $query, int $limit = 5): array
    {
        $items = $this->search('/search/code', $query, $limit);

        return array_map(static fn (array $item): array => [
            'title' => (string) $item['path'],
            'url' => (string) $item['html_url'],
            'snippet' => null,
        ], $items);
    }

    /** @return list<array<string, mixed>> */
    private function search(string $endpoint, string $query, int $limit): array
    {
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; ++$attempt) {
            $this->throttle();

            $response = $this->githubClient->request('GET', $endpoint, [
                'query' => ['q' => \sprintf('%s repo:%s', $query, $this->githubRepo), 'per_page' => $limit],
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($status === 200) {
                return \array_slice($data['items'] ?? [], 0, $limit);
            }

            if ($status === 403 && $attempt < self::MAX_RETRIES) {
                $this->logger->warning('GitHub search rate-limited on {endpoint}, backing off {seconds}s (attempt {attempt}/{max}).', [
                    'endpoint' => $endpoint,
                    'seconds' => self::RETRY_BACKOFF_SECONDS,
                    'attempt' => $attempt,
                    'max' => self::MAX_RETRIES,
                ]);
                sleep(self::RETRY_BACKOFF_SECONDS);
                continue;
            }

            throw new \RuntimeException(\sprintf('GitHub search request to %s failed (HTTP %d): %s', $endpoint, $status, $data['message'] ?? 'unknown error'));
        }

        return [];
    }

    private function throttle(): void
    {
        $elapsedNanos = hrtime(true) - $this->lastRequestAt;
        if ($elapsedNanos < self::MIN_INTERVAL_NANOS) {
            usleep((int) ((self::MIN_INTERVAL_NANOS - $elapsedNanos) / 1_000));
        }

        $this->lastRequestAt = hrtime(true);
    }
}
