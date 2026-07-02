<?php

declare(strict_types=1);

namespace App\Ingestion\GitHub;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/** Sleeps when the GitHub REST API rate limit is close to exhausted, based on response headers. */
final class RateLimitGuard
{
    private const REMAINING_THRESHOLD = 50;

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function throttle(ResponseInterface $response): void
    {
        $headers = $response->getHeaders(false);
        $remaining = isset($headers['x-ratelimit-remaining'][0]) ? (int) $headers['x-ratelimit-remaining'][0] : null;
        $reset = isset($headers['x-ratelimit-reset'][0]) ? (int) $headers['x-ratelimit-reset'][0] : null;

        if ($remaining === null || $reset === null || $remaining > self::REMAINING_THRESHOLD) {
            return;
        }

        $sleepSeconds = max(1, $reset - time());
        $this->logger->warning('GitHub rate limit low ({remaining} left), sleeping {seconds}s until reset.', [
            'remaining' => $remaining,
            'seconds' => $sleepSeconds,
        ]);

        sleep($sleepSeconds);
    }
}
