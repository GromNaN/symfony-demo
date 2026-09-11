# Disclose sensitive values with Symfony UX LiveComponent

This project shows how to render masked contact details on a Symfony page and
reveal them on demand, one explicit click at a time, with layered protections.

The tutorial builds the feature in five stages, always from the same starting
point:

1. a plain Twig template,
2. the same template turned into a component,
3. that component turned into a live component with a reveal action,
4. one security layer added at a time,
5. a final refinement where the component accepts a `Client` object instead of
   an id.

The complete application, at its final state, lives in [`app/`](app/). Every
code block links to its file in `app/`; intermediate blocks show the file at
that stage, which does not match the final `app/` version yet.

## The security layers

1. The sensitive value never enters the initial HTML.
2. The component takes a whole `Client` object as input, but custom hydration
   serializes only its id into `data-live-props-value`. The value is read
   server side on click.
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
  (the `importmap()` entry in [templates/base.html.twig](app/templates/base.html.twig)).
- `symfony/stimulus-bundle`: loads the JS controllers. The stock `live`
  controller handles every action round trip; you write no custom JavaScript.
- `symfony/ux-twig-component` and `symfony/ux-live-component`: the component
  machinery. Flex registers the bundles, the `/_components` route, the
  [importmap.php](app/importmap.php) entry and the
  [assets/controllers.json](app/assets/controllers.json) entry for you.
- `symfony/lock` provides the lock store. The recipe configures
  `framework.lock` with `LOCK_DSN=flock` (in
  [.env](app/.env) and [config/packages/lock.yaml](app/config/packages/lock.yaml)),
  which FrameworkBundle turns into the `lock.default.factory` service that the
  rate limiter reuses in step 3.
- `symfony/rate-limiter`: the token buckets behind `framework.rate_limiter`.

Install them all now; the earlier stages only use a subset, step 4 starts
adding the rest one by one.

## Step 3: configure the protection primitives

Create [config/packages/rate_limiter.yaml](app/config/packages/rate_limiter.yaml):

```yaml
framework:
    rate_limiter:
        # Shared budget for every disclosure on the page: 5 per minute, keyed
        # per visitor. The lock_factory serializes concurrent consume() calls
        # so a burst cannot race past the quota.
        disclose:
            policy: fixed_window
            limit: 5
            interval: '1 minute'
            # FrameworkBundle registers this factory for the "framework.lock"
            # option (LOCK_DSN, see config/packages/lock.yaml), so no custom
            # lock service is needed.
            lock_factory: lock.default.factory
```

The limiter stores its counters in the default `cache.app` pool (filesystem)
and wraps each `consume()` in the flock lock that FrameworkBundle configured
from `framework.lock` (the recipe set `LOCK_DSN=flock` in `.env`). No custom
lock service is required. This file is used from step 4.4 onward.

## Step 4: stage 1, a plain Twig template

Start from the simplest thing that works: a route that renders a list of
clients, each card masked on the server. No component, no JavaScript.

[app/src/Controller/LiveController.php](app/src/Controller/LiveController.php),
at this stage without the cookie:

```php
<?php

namespace App\Controller;

use App\Client\ClientStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LiveController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(ClientStore $clients): Response
    {
        return $this->render('index.html.twig', ['clients' => $clients->all()]);
    }
}
```

The data comes from [app/src/Client/ClientStore.php](app/src/Client/ClientStore.php),
an in-memory list of [app/src/Client/Client.php](app/src/Client/Client.php).

```php
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

The page template renders each client through a dedicated partial, keeping the
secret out of the loop body:

[templates/index.html.twig](app/templates/index.html.twig)

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
                {% include 'client_card.html.twig' with {client} only %}
            {% else %}
                <p class="empty-state">No clients yet.</p>
            {% endfor %}
        </div>
    </main>
{% endblock %}
```

And the masked card partial, `templates/client_card.html.twig`:

```twig
{# Only the display name is public. The contact details are NOT printed, so
   the page HTML and any scrape leaks nothing. #}
<div class="client-card">
    <span class="client-card__name">{{ client.name }}</span>
    <span class="client-card__contact">••••••••@•••••••&nbsp;&nbsp;·&nbsp;&nbsp;••••••••</span>
</div>
```

At this stage the page is correct but inert: the mask never lifts. The next
stage keeps the same output and only changes how the card is built.

## Step 5: stage 2, the template becomes a component

Turning the partial into a component encapsulates its markup, its props and
its CSS behind a named, reusable unit.

Create [app/src/Twig/Components/ClientCard.php](app/src/Twig/Components/ClientCard.php)
as a plain Twig component:

```php
<?php

namespace App\Twig\Components;

use App\Client\Client;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ClientCard
{
    public ?Client $client = null;
}
```

The [config/packages/twig_component.yaml](app/config/packages/twig_component.yaml)
mapping (`App\Twig\Components\` to `components/`) resolves two things:

- the component name is the short class name, `ClientCard`, usable in Twig as
  `<twig:ClientCard ... />`;
- the template is `templates/components/ClientCard.html.twig`, a copy of the
  partial from step 4 with two additions:

```twig
<div {{ attributes.defaults({class: 'client-card'}) }}>
    <span class="client-card__name">{{ client.name }}</span>
    <span class="client-card__contact">••••••••@•••••••&nbsp;&nbsp;·&nbsp;&nbsp;••••••••</span>
</div>
```

The `{{ attributes }}` variable lets the parent pass html attributes, classes
and data-attributes to the component root.

The page now uses the component in the loop:

```twig
{% for client in clients %}
    <twig:ClientCard :client="client" />
{% else %}
    ...
{% endfor %}
```

The rendering output is unchanged, but the card is now one unit that any page
can reuse. There is still no interactivity.

## Step 6: stage 3, the component becomes live and reveals on click

Now turn the component into a LiveComponent. The attribute changes, the
component gains a default action, its props become `LiveProp`, and it gets a
`reveal()` action that returns the value server side.

### 6.1 The class

[app/src/Twig/Components/ClientCard.php](app/src/Twig/Components/ClientCard.php):

```php
<?php

namespace App\Twig\Components;

use App\Client\ClientStore;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class ClientCard
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?int $clientId = null;

    #[LiveProp]
    public string $name = '';

    public bool $revealed = false;

    public string $email = '';

    public string $phone = '';

    public function __construct(private readonly ClientStore $clientStore)
    {
    }

    #[LiveAction]
    public function reveal(): void
    {
        $client = $this->clientStore->find($this->clientId ?? -1);
        $this->email = (string) ($client?->email ?? '');
        $this->phone = (string) ($client?->phone ?? '');
        $this->revealed = true;
    }

    #[LiveAction]
    public function hide(): void
    {
        $this->revealed = false;
    }
}
```

Three ideas matter here:

- `#[LiveProp]` props are the state that travels to the browser. Only the id
  and the name are props, and neither is sensitive.
- `$revealed`, `$email` and `$phone` are plain public properties: never
  serialized, reset on every mount, set by the action for the re-render of
  that request only.
- `#[LiveAction]` methods are the click entry points. They resolve their
  arguments like controllers, run server side, and the component re-renders
  afterwards.

### 6.2 The template

[app/templates/components/ClientCard.html.twig](app/templates/components/ClientCard.html.twig)
renders two states. The masked state is the initial HTML; the revealed state is
produced only by the `reveal()` response:

```twig
{% set revealed = revealed ?? false %}
<div {{ attributes.defaults({class: 'client-card'}) }}>
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
</div>
```

- `live_action('reveal')` expands to `data-action="live#action"` and
  `data-live-action-param="reveal"`: the stock `live` controller wires the
  button, no custom JavaScript.
- `data-loading="addAttribute(disabled)"` disables the button while the
  request is in flight, preventing a double click.

### 6.3 What a click does

1. The `live` controller sends a `POST` to
   `/_components/{component}/{action}`, here `/_components/ClientCard/reveal`,
   with the `X-Requested-With` and the `Accept: application/vnd.live-component+html`
   headers the server requires. The route comes from
   [config/routes/ux_live_component.yaml](app/config/routes/ux_live_component.yaml),
   which Flex imported from the LiveComponent bundle.
2. It sends the current props (`data-live-props-value`) back. The server
   re-computes the checksum embedded in `@checksum`, keyed on `APP_SECRET` from
   [.env](app/.env), and rejects tampered props with a 400.
3. It calls `reveal()` on a freshly hydrated component, then re-renders the
   component template.
4. It returns the new HTML, which the `live` controller merges into the DOM
   (morphdom).

At this stage the reveal works, but it is wide open: anyone can click as often
as they want, nothing is traced. The next stage fixes that layer by layer.

## Step 7: stage 4, one security layer at a time

Each layer is a small addition to the component, to the controller, or to the
config.

### 7.1 Identity: who is the visitor

The rate limiter of step 3 is keyed per visitor. Key it on a cookie the page
controller sets from the `?r=` query parameter, falling back to the client IP.

In [app/src/Controller/LiveController.php](app/src/Controller/LiveController.php),
add the cookie:

```php
public function index(Request $request, ClientStore $clients): Response
{
    $response = $this->render('index.html.twig', ['clients' => $clients->all()]);

    // The disclosure rate limiter keys on this cookie, so each visitor gets
    // an isolated budget across page reloads. Demo only: in a real app you
    // would key on the authenticated user.
    if ($request->query->has('r')) {
        $response->headers->setCookie(new Cookie('disclose_subject', (string) $request->query->get('r'), 0, '/', null, false, true));
    }

    return $response;
}
```

In the component, add a private `identity()` helper:

```php
private function identity(Request $request): string
{
    return $request->cookies->has('disclose_subject')
        ? 'reference:' . $request->cookies->get('disclose_subject')
        : 'ip:' . $request->getClientIp();
}
```

### 7.2 Rate limit: cap the reveals

Consume one token of the `disclose` limiter before anything else, and refuse
inline when the budget is dry. The constructor gains the limiter:

```php
public function __construct(
    #[Autowire(service: 'limiter.disclose')]
    private readonly RateLimiterFactory $rateLimiterFactory,
    private readonly ClientStore $clientStore,
) {
}
```

And `reveal()` starts with the acquisition of a token:

```php
$rateLimit = $this->rateLimiterFactory->create($identity)->consume();
if (!$rateLimit->isAccepted()) {
    $retryAfter = $rateLimit->getRetryAfter();
    $seconds = $retryAfter ? max(0, $retryAfter->getTimestamp() - time()) : 1;
    $this->error = 'Rate limit exceeded. Try again later.';

    return;
}
```

The token is consumed before the policy and before any audit write, so a
refused request still burns a token and a burst cannot flood the audit trail.

### 7.3 Lock: make the quota race safe

`consume()` reads and writes the counter in the cache. Two simultaneous clicks
from the same visitor would both read the same remaining budget and raise it.
The `lock_factory: lock.default.factory` option of [config/packages/rate_limiter.yaml](app/config/packages/rate_limiter.yaml)
serializes concurrent `consume()` calls under the flock lock that
[config/packages/lock.yaml](app/config/packages/lock.yaml) provides. This layer
is pure configuration, no code in the component.

### 7.4 Authorization: gate every reveal

Add an explicit policy step in front of the value read. The demo grants
everyone; a real app evaluates a Security attribute (role or voter) here.

```php
if (!$this->isGranted()) {
    $this->error = 'Access denied.';

    return;
}

private function isGranted(): bool
{
    return true;
}
```

### 7.5 Audit: trace every attempt

Inject the `LoggerInterface` in the constructor and add one structured log per
outcome, with the identity and the target context.

```php
public function __construct(
    ...,
    private readonly LoggerInterface $logger,
) {
}
```

In `reveal()`, each branch now writes its line:

```php
$this->logger->warning('Protected value disclosure: rate_limited', ['identity' => $identity, 'context' => $context, 'retry_after' => $seconds]);
$this->logger->warning('Protected value disclosure: auth_denied', ['identity' => $identity, 'context' => $context]);
$this->logger->info('Protected value disclosure: attempt', ['identity' => $identity, 'context' => $context]);
$this->logger->info('Protected value disclosure: success', ['identity' => $identity, 'context' => $context]);
```

`$context` gathers the target so a bulk harvest is easy to spot:

```php
$context = ['class' => $client::class, 'id' => $client->id];
```

### 7.6 Events: let consumers plug in

Domain events decouple the reveal from audit stores, metrics or alerts. Add
[app/src/Event/ClientDiscloseEvent.php](app/src/Event/ClientDiscloseEvent.php):

```php
<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class ClientDiscloseEvent extends Event
{
    public const ATTEMPT = 'client_card.attempt';
    public const SUCCESS = 'client_card.success';
    public const REJECTED = 'client_card.rejected';

    public function __construct(
        public readonly string $class,
        public readonly string|int $id,
        public readonly string $status,
        public readonly ?object $subject = null,
    ) {}
}
```

Inject the `EventDispatcherInterface` and dispatch one event per outcome:

```php
$this->eventDispatcher->dispatch(new ClientDiscloseEvent($client::class, $client->id, 'rate_limited'), ClientDiscloseEvent::REJECTED);
$this->eventDispatcher->dispatch(new ClientDiscloseEvent($client::class, $client->id, 'auth_denied', $client), ClientDiscloseEvent::REJECTED);
$this->eventDispatcher->dispatch(new ClientDiscloseEvent($client::class, $client->id, 'attempt', $client), ClientDiscloseEvent::ATTEMPT);
$this->eventDispatcher->dispatch(new ClientDiscloseEvent($client::class, $client->id, 'success', $client), ClientDiscloseEvent::SUCCESS);
```

### 7.7 The final component

With every layer in place, `reveal()` reads as a checkpoint chain. The browser
keeps seeing only the id and the name in its state; the email and the phone
are read server side and rendered for the single re-render that follows a
click. The whole chain is verified end to end in step 9.

## Step 8: stage 5, accept the Client object

The id-and-name props work, but a nicer API passes the whole object, matching
how the page thinks about its data. Custom hydration gives this without
weakening the security: the object is dehydrated to its id, then rebuilt
server side on every request.

Replace the two props with one:

```php
#[LiveProp(hydrateWith: 'hydrateClient', dehydrateWith: 'dehydrateClient')]
public ?Client $client = null;

public function hydrateClient(int $data): ?Client
{
    return $this->clientStore->find($data);
}

public function dehydrateClient(Client $client): int
{
    return $client->id;
}
```

- `dehydrateClient()` turns the object into its id when the component state is
  written to the page. Without it, the whole `Client`, email and phone
  included, would end up in `data-live-props-value`.
- `hydrateClient()` rebuilds the object server side from that id on every
  action, so the template and the actions always see a full `Client`.

The `email` and `phone` local props disappear: the template reads them from
the hydrated object, which exists only server side. The page no longer passes
an id and a name, just the object:

```twig
<twig:ClientCard :client="client" />
```

The template becomes a slightly cleaner version of the one in step 6.2:

```twig
{% set revealed = revealed ?? false %}
<div {{ attributes.defaults({class: 'client-card', 'data-live-card': ''}) }}>
    <span class="client-card__name">{{ client.name }}</span>
    ...
    <span class="client-card__contact">
        {% if revealed %}
            <span class="client-card__detail">{{ client.email }}</span><span class="client-card__sep" aria-hidden="true">·</span><span class="client-card__detail">{{ client.phone }}</span>
        {% else %}
            ••••••••@•••••••&nbsp;&nbsp;·&nbsp;&nbsp;••••••••
        {% endif %}
    </span>
</div>
```

The styles in [app/assets/styles/app.css](app/assets/styles/app.css) drive the
visual states; the rules that matter for security are the two icon display
states and the spinner on `aria-busy` (the stock `live` controller marks the
component root with `aria-busy` while a request is in flight):

```css
.client-card__toggle .disclose-icon--eye-slash { display: inline-block; }
.client-card[data-live-card] .disclose-icon--eye { display: inline-block; }

.client-card[data-live-card][aria-busy='true'] .disclose-spinner { display: block; }
.client-card[data-live-card][aria-busy='true'] .disclose-icon { opacity: 0.3; }
```

## Step 9: the final files and the verify checklist

The appendix lists each final file; they are all in `app/`:

- [app/src/Twig/Components/ClientCard.php](app/src/Twig/Components/ClientCard.php)
- [app/templates/components/ClientCard.html.twig](app/templates/components/ClientCard.html.twig)
- [app/src/Controller/LiveController.php](app/src/Controller/LiveController.php)
- [app/templates/index.html.twig](app/templates/index.html.twig)
- [app/src/Event/ClientDiscloseEvent.php](app/src/Event/ClientDiscloseEvent.php)
- [app/src/Client/Client.php](app/src/Client/Client.php) and
  [app/src/Client/ClientStore.php](app/src/Client/ClientStore.php)
- [app/config/packages/rate_limiter.yaml](app/config/packages/rate_limiter.yaml)
- [app/assets/styles/app.css](app/assets/styles/app.css)

Run it:

```bash
php bin/console cache:clear
php -S 127.0.0.1:8000 -t public public/index.php
```

Open `http://127.0.0.1:8000/?r=demo`.

- View the source: no `example.org` and no `+1 555` string appears, and each
  card `data-live-props-value` contains only `client`, `@attributes` and a
  checksum.
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

| Layer | Added in | Guarantee |
| --- | --- | --- |
| Value kept off the HTML | stage 1 (step 4) | View source and scrapes leak nothing |
| Minimal client state | stage 3 + 5 (steps 6 and 8) | Only the id is serialized, under a checksum |
| Rate limit | layer 7.2 | A bulk harvest hits a small quota |
| Lock | layer 7.3 | Concurrent clicks cannot race past the quota |
| Authorization | layer 7.4 | The policy is enforced server side at every click |
| Audit trail | layer 7.5 | Abuse is loud and traceable |
| Domain events | layer 7.6 | Plug audit stores, metrics, alerting |
| Inline errors | layers 7.2 and 7.4 | Failures stay inside the component UI |
| Same-origin protection | stage 3 | Actions require `X-Requested-With` and the custom `Accept` header |

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

The full application, at its final state, is in `app/`. Copy each file from its
linked source, or run the whole `app/` as a reference, and re-verify with step
9.
