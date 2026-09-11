# Disclose sensitive values with Symfony UX LiveComponent

This project shows how to render masked contact details on a Symfony page and
reveal them on demand, one explicit click at a time, with layered protections.
The complete application lives in [`app/`](app/) and this file is the
step-by-step tutorial that rebuilds it from scratch.

## The security layers

1. The sensitive value never enters the initial HTML.
2. Only the record id and a display name travel in the client-side state
   (`data-live-props-value`). The value is read server-side on click.
3. Every reveal is rate limited per visitor (5 per minute by default).
4. The rate limiter is serialized by a lock, so concurrent clicks cannot race
   past the quota.
5. An authorization step gates every reveal (the demo grants everyone, a real
   app evaluates a Security attribute).
6. Every attempt is audited with the identity, the target and the outcome.
7. Local domain events let you plug audit stores, metrics or alerts.
8. Failures (rate limit, access denied) are handled inline, inside the
   component.

## Requirements

- PHP 8.4 or newer
- Composer
- Network access to install the packages from Packagist

## Step 1: create the application

```bash
composer create-project symfony/skeleton:^7.4 disclose-live
cd disclose-live
```

Set a value for `APP_SECRET` in `.env` if it is empty:

```bash
php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'
```

Copy the printed value into the `APP_SECRET=` line of `.env`.

## Step 2: add the packages

```bash
composer require twig symfony/asset-mapper symfony/stimulus-bundle \
    symfony/ux-twig-component symfony/ux-live-component \
    symfony/lock symfony/rate-limiter
```

What each package brings:

- `twig` and `symfony/asset-mapper`: templates and the frontend asset pipeline
  (the `importmap()` entry in `base.html.twig`).
- `symfony/stimulus-bundle`: loads the JS controllers. The stock `live`
  controller handles every action round trip; you write no custom JavaScript.
- `symfony/ux-twig-component` and `symfony/ux-live-component`: the component
  machinery. Flex registers the bundles, the `/_components` route, the
  `importmap.php` entry and the `assets/controllers.json` entry for you.
- `symfony/lock` and `symfony/rate-limiter`: the protection primitives used in
  step 3.

## Step 3: configure the rate limiter and the lock

Create `config/packages/rate_limiter.yaml`:

```yaml
framework:
    rate_limiter:
        # Shared budget for every disclosure on the page: 5 per minute, keyed
        # per visitor. The lock_factory serializes concurrent consume() calls
        # so a burst cannot race past the quota.
        ux_disclose:
            policy: fixed_window
            limit: 5
            interval: '1 minute'
            lock_factory: app.lock.factory

services:
    # Race-safe disclosures: consume() runs under a filesystem lock stored in
    # the cache directory. Production should use a shared store (Redis, DBAL)
    # and a shared cache pool so quotas survive restarts and scale to several
    # instances.
    app.lock.store:
        class: Symfony\Component\Lock\Store\FlockStore
        arguments: ['%kernel.cache_dir%/locks']

    app.lock.factory:
        class: Symfony\Component\Lock\LockFactory
        arguments: ['@app.lock.store']
```

The limiter stores its counters in the default `cache.app` pool (filesystem)
and wraps each `consume()` in a filesystem lock.

## Step 4: the reference data

A `Client` is a plain value object and `ClientStore` holds the in-memory list.
Swap this for Doctrine, an API or any other source in a real application.

`src/Client/Client.php`:

```php
<?php

namespace App\Client;

/**
 * A reference record whose contact details are sensitive.
 */
final class Client
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
    ) {}
}
```

`src/Client/ClientStore.php`:

```php
<?php

namespace App\Client;

/**
 * Reference data for the demo: a handful of in-memory clients. A real app
 * would load the record from Doctrine, an API or any other source.
 */
final class ClientStore
{
    /** @var Client[] */
    private array $clients = [];

    public function __construct()
    {
        foreach (range(1, 6) as $i) {
            $this->clients[] = new Client(
                $i,
                sprintf('Client %d', $i),
                sprintf('client%03d@example.org', $i),
                sprintf('+1 555 010 %04d', $i),
            );
        }
    }

    /** @return Client[] */
    public function all(): array
    {
        return $this->clients;
    }

    public function find(int $id): ?Client
    {
        foreach ($this->clients as $client) {
            if ($client->id === $id) {
                return $client;
            }
        }

        return null;
    }
}
```

## Step 5: the domain event

`src/Event/ClientDiscloseEvent.php` mirrors the lifecycle of a disclosure so
consumers can subscribe to the same three moments regardless of the mechanism
that triggered them:

```php
<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired around a card disclosure so applications can plug their own audit
 * storage, notifications or metrics.
 */
final class ClientDiscloseEvent extends Event
{
    /**
     * Dispatched right before the value is revealed, once the policy passed.
     */
    public const ATTEMPT = 'client_card.attempt';

    /**
     * Dispatched after the value was revealed.
     */
    public const SUCCESS = 'client_card.success';

    /**
     * Dispatched when the reveal is rejected (rate limit or access denied);
     * the status carries the exact reason.
     */
    public const REJECTED = 'client_card.rejected';

    public function __construct(
        public readonly string $class,
        public readonly string|int $id,
        public readonly string $status,
        public readonly ?object $subject = null,
    ) {}
}
```

## Step 6: the LiveComponent

`src/Twig/Components/ClientCard.php` is the heart of the feature. Note which
fields are `LiveProp` and which are not: only the non-sensitive identifier and
name reach the browser. Contact details, the revealed flag and the inline error
are plain public properties, so they never appear in `data-live-props-value`
and are reset on every request.

```php
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
```

The order matters. The rate limit is consumed before any lookup of the policy
and before the audit success, so a denied request still burns a token and a
burst cannot flood the audit trail. Authorization runs before any value is
read. Attempt and success are audited and dispatched around the actual reveal.

LiveComponent actions are "controllers": they can inject the `Request` and any
service, and every request is rejected unless it carries the
`X-Requested-With` and the custom `Accept` headers the stock `live` controller
sends, which gives same-origin protection out of the box.

## Step 7: the component template

`templates/components/ClientCard.html.twig` renders two states. The masked
state is the initial HTML; the revealed state is produced only by the
`reveal()` response.

```twig
{% set revealed = revealed ?? false %}
<div {{ attributes.defaults({class: 'client-card', 'data-live-card': ''}) }}>
    <span class="client-card__name">{{ name }}</span>

    <button
        type="button"
        class="client-card__toggle"
        data-loading="addAttribute(disabled)"
        aria-label="{{ revealed ? 'Hide contact' : 'Reveal contact' }}"
        title="{{ revealed ? 'Hide contact' : 'Reveal contact' }}"
        {{ live_action(revealed ? 'hide' : 'reveal') }}
    >
        {% if revealed %}
            <svg class="disclose-icon disclose-icon--eye" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
        {% else %}
            <svg class="disclose-icon disclose-icon--eye-slash" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
        {% endif %}
        <span class="disclose-spinner" aria-hidden="true"></span>
    </button>

    <span class="client-card__contact">
        {% if revealed %}
            <span class="client-card__detail">{{ email }}</span><span class="client-card__sep" aria-hidden="true">·</span><span class="client-card__detail">{{ phone }}</span>
        {% else %}
            ••••••••@•••••••&nbsp;&nbsp;·&nbsp;&nbsp;••••••••
        {% endif %}
    </span>

    {% if error %}
        <p class="client-card__error" role="alert">{{ error }}</p>
    {% endif %}
</div>
```

The stock `live` controller sets `aria-busy` on the component root while a
request is in flight, so the spinner is always present in the markup and only
shown during a round trip. The `{% set revealed = revealed ?? false %}`
guard makes the initial mount safe even before the property is exposed.

## Step 8: the page

`src/Controller/LiveController.php` renders the list and sets a per-visitor
cookie from the `r` query parameter:

```php
<?php

namespace App\Controller;

use App\Client\ClientStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LiveController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(Request $request, ClientStore $clients): Response
    {
        $response = $this->render('index.html.twig', ['clients' => $clients->all()]);

        // The disclosure rate limiter keys on this cookie, so each visitor
        // gets an isolated budget across page reloads. Demo only: in a real
        // app you would key on the authenticated user.
        if ($request->query->has('r')) {
            $response->headers->setCookie(new Cookie('ux_disclose_subject', (string) $request->query->get('r'), 0, '/', null, false, true));
        }

        return $response;
    }
}
```

`templates/index.html.twig` loops over the cards:

```twig
{% extends 'base.html.twig' %}

{% block title %}Clients - masked, disclosed on demand{% endblock %}

{% block body %}
    <main class="shell">
        <header class="page-head">
            <h1>Clients</h1>
            <p>
                Contact details are masked and revealed on demand: the value never
                reaches the initial HTML, each reveal is rate limited and locked,
                and every attempt is audited and dispatched as an event.
            </p>
        </header>

        <div class="client-list">
            {% for client in clients %}
                <twig:ClientCard :clientId="client.id" :name="client.name" />
            {% else %}
                <p class="empty-state">No clients yet.</p>
            {% endfor %}
        </div>
    </main>
{% endblock %}
```

Only the id and the name are passed to the component: the email and the phone
leave the server only after a click.

## Step 9: run and verify

```bash
php bin/console cache:clear
php -S 127.0.0.1:8000 -t public public/index.php
```

Open `http://127.0.0.1:8000/?r=demo`.

- View the source: no `example.org` and no `+1 555` string appears, and each
  card `data-live-props-value` contains only `clientId`, `name`, `@attributes`
  and a checksum.
- Click an eye: the email and phone appear without a full page reload, and a
  second click masks them again.
- Click five eyes (or the same card five times): the sixth reveal shows the
  inline `Rate limit exceeded. Try again later.` message. A different visitor
  reference, `?r=demo2`, starts with a fresh budget.
- The server output shows the audit trail: five `attempt` + `success` lines
  then one `rate_limited` line, each with the identity and the target context.

Subscribe to the events to plug your own audit store, metrics or alerting. The
subscriber below is registered automatically by the `App\` service scan:

```php
<?php
// src/EventSubscriber/DiscloseAuditSubscriber.php

namespace App\EventSubscriber;

use App\Event\ClientDiscloseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DiscloseAuditSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ClientDiscloseEvent::ATTEMPT => 'onDisclose',
            ClientDiscloseEvent::SUCCESS => 'onDisclose',
            ClientDiscloseEvent::REJECTED => 'onDisclose',
        ];
    }

    public function onDisclose(ClientDiscloseEvent $event): void
    {
        // Persist to your audit store here.
    }
}
```

## How each layer maps to a guarantee

| Layer | Location | Guarantee |
| --- | --- | --- |
| Value kept off the HTML | `reveal()` and template | View source and scrapes leak nothing |
| Minimal client state | `LiveProp clientId` and `name` | The props are checksum protected, the value is never in them |
| Rate limit | `reveal()`, step 1 | A bulk harvest hits a small quota |
| Lock | `rate_limiter.yaml`, `lock_factory` | Concurrent clicks cannot race past the quota |
| Authorization | `reveal()`, step 2 | The policy is enforced server side at every click |
| Audit trail | `reveal()`, steps 1/2/3/5 | Abuse is loud and traceable |
| Domain events | dispatches in `reveal()` | Plug audit stores, metrics, alerting |
| Inline errors | `error` property | Failures stay inside the component UI |
| Same-origin protection | stock `live` controller | Actions require `X-Requested-With` and the custom `Accept` header |

## Production notes

- Use a shared cache pool and a shared lock store (Redis or DBAL) so the quotas
  survive restarts and multiple instances.
- Key the limiter on the authenticated user instead of a visitor cookie.
- Replace `isGranted()` with a real policy: roles or voters on the Security
  component, possibly field aware.
- Add a lifetime to what the client holds, so a captured page cannot be
  replayed forever.
- Tradeoff: hiding then re-revealing a card consumes another token, because
  every action is a fresh server round trip. Prefetching or caching the value
  client side would bypass the audit each reveal is meant to produce.

## Where the code lives

The full application is in `app/`. The code blocks above match it exactly, so
any of them can be copied into a fresh skeleton and re-verified with step 9.
