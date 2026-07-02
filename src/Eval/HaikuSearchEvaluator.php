<?php

declare(strict_types=1);

namespace App\Eval;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Puts a small, cheap LLM (Claude Haiku, via an internal Anthropic-compatible gateway) in the
 * search loop: it reformulates the original question for a given backend, then answers using
 * only that backend's results. Direct HTTP calls rather than the symfony/ai-anthropic-platform
 * bridge, because the bridge hardcodes the `x-api-key` header while our gateway (Grove) expects
 * `api-key` (confirmed by testing both against the real endpoint before writing this class).
 */
final class HaikuSearchEvaluator
{
    private const MODEL = 'claude-haiku-4-5';

    public function __construct(private readonly HttpClientInterface $groveClient)
    {
    }

    /**
     * Answers from the model's own training knowledge, with no retrieved context. Used to check
     * whether a given question is even a fair test of retrieval — if the model already names a
     * specific, correct EasyAdminBundle issue/PR/method on its own, comparing search backends on
     * that question doesn't tell us much.
     */
    public function answerFromKnowledge(string $question): string
    {
        return $this->complete(
            system: 'You answer questions about the EasyAdminBundle open source PHP project (github.com/EasyCorp/EasyAdminBundle) '
                . 'using only what you already know from training. Do not guess: if you are not confident, say so explicitly. '
                . 'If you do recall a specific GitHub issue number, pull request number, class or method name, cite it precisely.',
            user: $question,
        );
    }

    /** Reformulates the question into a query adapted to one specific search backend. */
    public function formulateQuery(string $question, string $backendDescription): string
    {
        return trim($this->complete(
            system: 'You are about to search a knowledge base to answer a user question. '
                . 'The search backend available to you is: ' . $backendDescription . ' '
                . 'Reply with ONLY the search query text you would type, nothing else — no quotes, no explanation.',
            user: $question,
            maxTokens: 100,
        ));
    }

    /**
     * @param list<array{title: string, url: ?string, snippet: string}> $results
     */
    public function answerWithContext(string $question, array $results): string
    {
        if ($results === []) {
            return '(no search results were returned, so no answer was attempted)';
        }

        $context = implode("\n\n", array_map(
            static fn (array $r, int $i): string => \sprintf("[%d] %s\nURL: %s\n%s", $i + 1, $r['title'], $r['url'] ?? '(no url)', $r['snippet']),
            $results,
            array_keys($results),
        ));

        return $this->complete(
            system: 'Answer the user question using ONLY the numbered search results provided below. '
                . "If the results don't contain enough information to answer, say so explicitly rather than guessing or using outside knowledge.\n\n"
                . "Search results:\n" . $context,
            user: $question,
        );
    }

    private function complete(string $system, string $user, int $maxTokens = 500): string
    {
        $response = $this->groveClient->request('POST', 'v1/messages', [
            'json' => [
                'model' => self::MODEL,
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ],
        ]);

        $data = $response->toArray();

        return (string) ($data['content'][0]['text'] ?? '');
    }
}
