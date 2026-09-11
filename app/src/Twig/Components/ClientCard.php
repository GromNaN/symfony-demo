<?php

namespace App\Twig\Components;

use App\Client\Client;
use App\Client\ClientStore;
use App\Event\ClientDiscloseEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Renders a masked client card. The contact details never enter the initial
 * HTML nor the component props: the reveal action reads them server-side and
 * renders them only for its own response, after the security chain below.
 */
#[AsLiveComponent]
final class ClientCard
{
    use DefaultActionTrait;

    /**
     * Only these two fields travel in data-live-props-value: the identifier
     * and the display name. Neither is sensitive.
     */
    #[LiveProp]
    public ?int $clientId = null;

    #[LiveProp]
    public string $name = '';

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

    /**
     * Contact details, set by the reveal action for the re-render only, so
     * they stay out of the client-side state.
     */
    public string $email = '';

    public string $phone = '';

    public function __construct(
        // The framework rate limiter "ux_disclose": fixed_window, 5 per
        // minute, serialized by a lock (see config/packages/rate_limiter.yaml).
        #[Autowire(service: 'limiter.ux_disclose')]
        private readonly RateLimiterFactory $rateLimiterFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
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

        $client = $this->clientStore->find($this->clientId ?? -1);
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
            $this->eventDispatcher->dispatch(new ClientDiscloseEvent($client::class, $client->id, 'rate_limited'), ClientDiscloseEvent::REJECTED);
            $this->error = 'Rate limit exceeded. Try again later.';

            return;
        }

        // 2. Authorization: an explicit policy step. The demo grants everyone;
        //    a real app would evaluate a Security attribute (voter, role).
        if (!$this->isGranted()) {
            $this->logger->warning('Protected value disclosure: auth_denied', ['identity' => $identity, 'context' => $context]);
            $this->eventDispatcher->dispatch(new ClientDiscloseEvent($client::class, $client->id, 'auth_denied', $client), ClientDiscloseEvent::REJECTED);
            $this->error = 'Access denied.';

            return;
        }

        // 3. Attempt audit and event, right before the value is revealed.
        $this->logger->info('Protected value disclosure: attempt', ['identity' => $identity, 'context' => $context]);
        $this->eventDispatcher->dispatch(new ClientDiscloseEvent($client::class, $client->id, 'attempt', $client), ClientDiscloseEvent::ATTEMPT);

        // 4. Reveal: the email/phone are rendered only by this response.
        $this->email = $client->email;
        $this->phone = $client->phone;
        $this->revealed = true;
        $this->error = '';

        // 5. Success audit and event.
        $this->logger->info('Protected value disclosure: success', ['identity' => $identity, 'context' => $context]);
        $this->eventDispatcher->dispatch(new ClientDiscloseEvent($client::class, $client->id, 'success', $client), ClientDiscloseEvent::SUCCESS);
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
        return $request->cookies->has('ux_disclose_subject')
            ? 'reference:' . $request->cookies->get('ux_disclose_subject')
            : 'ip:' . $request->getClientIp();
    }
}
