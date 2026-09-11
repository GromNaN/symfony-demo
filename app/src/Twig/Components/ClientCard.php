<?php

namespace App\Twig\Components;

use App\Client\Client;
use App\Client\ClientStore;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Renders a masked client card. The component takes the whole Client object as
 * input, but custom hydration dehydrates it to its id and re-fetches the
 * record server side, so only the id ever travels in data-live-props-value.
 * The contact details never enter the initial HTML: the reveal action renders
 * them only for its own response, after the security chain below.
 */
#[AsLiveComponent]
final class ClientCard
{
    use DefaultActionTrait;

    /**
     * The client whose contact details this card can disclose. Only its id is
     * serialized into data-live-props-value (see hydrateClient and
     * dehydrateClient); the object is rebuilt server side on every request.
     */
    #[LiveProp(hydrateWith: 'hydrateClient', dehydrateWith: 'dehydrateClient')]
    public ?Client $client = null;

    /**
     * Server-side disclosure state. Deliberately not a LiveProp so it never
     * leaves the server: it is reset on every mount and only mutated by the
     * reveal/hide actions for the re-render of their own request.
     */
    public bool $revealed = false;

    /**
     * Inline error shown when the reveal was refused (rate limit, access).
     */
    public string $error = '';

    public function __construct(
        // The framework rate limiter "disclose": fixed_window, 5 per minute,
        // serialized by FrameworkBundle's lock (see
        // config/packages/rate_limiter.yaml).
        #[Autowire(service: 'limiter.disclose')]
        private readonly RateLimiterFactory $rateLimiterFactory,
        private readonly LoggerInterface $logger,
        private readonly ClientStore $clientStore,
    ) {}

    #[LiveAction]
    public function reveal(Request $request): void
    {
        // Belt and suspenders on top of the disabled-while-loading button: a
        // second click that slips through must not consume another token.
        if ($this->revealed) {
            return;
        }

        // A local copy narrows the nullable prop (Client|null) to a plain
        // Client after the guard, so the rest of the method needs no null-safe
        // access. The prop is nullable because the masked state has no client
        // until the parent passes one.
        $client = $this->client;
        if (null === $client) {
            $this->error = 'This client no longer exists.';

            return;
        }

        $context = ['class' => $client::class, 'id' => $client->id];
        $identity = $this->identity($request);

        // 1. Rate limit first: one token is consumed under the lock, so a
        //    refused request never reaches the policy or the audit.
        $rateLimit = $this->rateLimiterFactory->create($identity)->consume();
        if (!$rateLimit->isAccepted()) {
            $retryAfter = $rateLimit->getRetryAfter();
            $seconds = $retryAfter ? max(0, $retryAfter->getTimestamp() - time()) : 1;
            $this->logger->warning('Protected value disclosure: rate_limited', ['identity' => $identity, 'context' => $context, 'retry_after' => $seconds]);
            $this->error = 'Rate limit exceeded. Try again later.';

            return;
        }

        // 2. Authorization: an explicit policy step. The demo grants everyone;
        //    a real app would evaluate a Security attribute (voter, role).
        if (!$this->isGranted()) {
            $this->logger->warning('Protected value disclosure: auth_denied', ['identity' => $identity, 'context' => $context]);
            $this->error = 'Access denied.';

            return;
        }

        // 3. Attempt audit, right before the value is revealed.
        $this->logger->info('Protected value disclosure: attempt', ['identity' => $identity, 'context' => $context]);

        // 4. Reveal: the email/phone are rendered only by this response, from
        //    the server-side Client object.
        $this->revealed = true;
        $this->error = '';

        // 5. Success audit.
        $this->logger->info('Protected value disclosure: success', ['identity' => $identity, 'context' => $context]);
    }

    #[LiveAction]
    public function hide(): void
    {
        // Pure UI toggle: hiding back is intentionally not rate limited nor
        // audited. Tradeoff: re-revealing the same card consumes another token.
        $this->revealed = false;
        $this->error = '';
    }

    /**
     * Rebuilds the client server side from the dehydrated id.
     */
    public function hydrateClient(int $data): ?Client
    {
        return $this->clientStore->find($data);
    }

    /**
     * Serializes the client as its id only: the contact details never reach
     * data-live-props-value.
     */
    public function dehydrateClient(Client $client): int
    {
        return $client->id;
    }

    /**
     * Demo policy: everyone may reveal contact details. A real app would
     * evaluate a Security attribute here.
     */
    private function isGranted(): bool
    {
        return true;
    }

    /**
     * Keys the rate limiter on a per-visitor cookie reference, then falls back
     * to the client IP. The cookie is set by the page controller via "?r=".
     */
    private function identity(Request $request): string
    {
        return $request->cookies->has('disclose_subject')
            ? 'reference:' . $request->cookies->get('disclose_subject')
            : 'ip:' . $request->getClientIp();
    }
}
