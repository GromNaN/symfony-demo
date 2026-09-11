# Disclose sensitive values with Symfony UX LiveComponent

This project shows how to render masked contact details on a Symfony page and
reveal them on demand, one explicit click at a time, with layered protections.

The tutorial builds the feature in five stages, from the smallest useful
starting point, and installs packages only when a stage needs them:

1. a plain Twig template (no component, no package beyond templates),
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
7. Failures (rate limit, access denied) are handled inline, inside the
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

Flex generates `APP_SECRET` automatically when the project is created,
writing it into `app/.env.local`, a file that is gitignored. If yours is still
empty, generate one and store it there:

```bash
php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'
```

Because `.env.local` is not committed, a fresh clone only needs its own local
secret. The LiveComponent checksums of step 4 are keyed on this secret.

## Step 2: stage 1, a plain Twig template

The skeleton has no template engine yet. Install Twig for the templates and
AssetMapper for the `importmap()` asset pipeline referenced by
[templates/base.html.twig](app/templates/base.html.twig):

```bash
composer require twig symfony/asset-mapper
```

Then build the simplest thing that works: a route that renders a list of
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
                and every attempt is audited.
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

## Step 3: stage 2, the template becomes a component

Components need one more package:

```bash
composer require symfony/ux-twig-component
```

Turning the partial into a component encapsulates its markup, its props and
its CSS behind a named, reusable unit.

The recipe registers the bundle and creates
[config/packages/twig_component.yaml](app/config/packages/twig_component.yaml),
whose mapping (`App\Twig\Components\` to `components/`) resolves two things:

- the component name is the short class name, `ClientCard`, usable in Twig as
  `<twig:ClientCard ... />`;
- the template is `templates/components/ClientCard.html.twig`.

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

Its template is a copy of the partial from step 2 with one addition, the
`{{ attributes }}` variable that lets the parent pass html attributes, classes
and data-attributes to the component root:

```twig
<div {{ attributes.defaults({class: 'client-card'}) }}>
    <span class="client-card__name">{{ client.name }}</span>
    <span class="client-card__contact">••••••••@•••••••&nbsp;&nbsp;·&nbsp;&nbsp;••••••••</span>
</div>
```

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

## Step 4: stage 3, the component becomes live and reveals on click

Interactivity needs the LiveComponent machinery, which brings the Stimulus
bridge with it:

```bash
composer require symfony/ux-live-component symfony/ux-icons
```

The recipes do the wiring for you: they register the bundles, import the
`/_components` route, add the stock `live` controller to
[importmap.php](app/importmap.php) and to
[assets/controllers.json](app/assets/controllers.json), and create
[config/packages/ux_icons.yaml](app/config/packages/ux_icons.yaml). The eye
icons come from a local icon set in [app/assets/icons/bi](app/assets/icons/bi),
so the rendered page resolves them without any network call, unlike inline
SVGs that would have to be duplicated on every card.

Now turn the component into a LiveComponent. The attribute changes, the
component gains a default action, its props become `LiveProp`, and it gets a
`reveal()` action that returns the value server side.

### 4.1 The class

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

### 4.2 The template

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
            {{ ux_icon('bi:eye', {class: 'disclose-icon disclose-icon--eye'}) }}
        {% else %}
            {{ ux_icon('bi:eye-slash', {class: 'disclose-icon disclose-icon--eye-slash'}) }}
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
- The eye and eye-slash glyphs come from the local `bi` icon set, rendered by
  `ux_icon()` with the same `disclose-icon` classes the CSS targets.

### 4.3 What a click does

1. The `live` controller sends a `POST` to
   `/_components/{component}/{action}`, here `/_components/ClientCard/reveal`,
   with the `X-Requested-With` and the `Accept: application/vnd.live-component+html`
   headers the server requires. The route comes from
   [config/routes/ux_live_component.yaml](app/config/routes/ux_live_component.yaml),
   which Flex imported from the LiveComponent bundle.
2. It sends the current props (`data-live-props-value`) back. The server
   re-computes the checksum embedded in `@checksum`, keyed on `APP_SECRET`,
   and rejects tampered props with a 400. This
   is the signature of the component state: a client cannot edit the id to
   target another record, because any edit breaks the checksum. The signature
   does not expire, so a captured copy can still be traded at the rate limit;
   the limiter and the audit bound the replay.
3. It calls `reveal()` on a freshly hydrated component, then re-renders the
   component template.
4. It returns the new HTML, which the `live` controller merges into the DOM
   (morphdom).

At this stage the reveal works, but it is wide open: anyone can click as often
as they want, nothing is traced. The next stage fixes that layer by layer.

## Step 5: stage 4, one security layer at a time

Each layer is a small addition to the component, to the controller, or to the
config, plus a package only when one is needed.

### 5.1 Identity: who is the visitor

The rate limiter keyed per visitor needs an identity. Key it on a cookie the
page controller sets from the `?r=` query parameter, falling back to the
client IP.

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

### 5.2 Rate limit: cap the reveals

Install the rate limiting component:

```bash
composer require symfony/rate-limiter
```

Declare the limiter in
[config/packages/rate_limiter.yaml](app/config/packages/rate_limiter.yaml):

```yaml
framework:
    rate_limiter:
        disclose:
            policy: fixed_window
            limit: 5
            interval: '1 minute'
```

The counters live in the default `cache.app` pool (filesystem). Then inject the
limiter in the component constructor and consume one token before anything
else:

```php
public function __construct(
    #[Autowire(service: 'limiter.disclose')]
    private readonly RateLimiterFactory $rateLimiterFactory,
    private readonly ClientStore $clientStore,
) {
}
```

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

### 5.3 Lock: make the quota race safe

`consume()` reads and writes the counter in the cache. Two simultaneous clicks
from the same visitor would both read the same remaining budget and raise it.
The Lock component serializes those reads:

```bash
composer require symfony/lock
```

The recipe sets `LOCK_DSN=flock` in [.env](app/.env) and creates
[config/packages/lock.yaml](app/config/packages/lock.yaml). FrameworkBundle
turns that into a `lock.default.factory` service. Declare it on the limiter:

```yaml
framework:
    rate_limiter:
        disclose:
            policy: fixed_window
            limit: 5
            interval: '1 minute'
            lock_factory: lock.default.factory
```

This layer is pure configuration, no code in the component.

The lock is scoped to the token take, never to the backend work.
`RateLimiterFactory` builds one lock per limiter key and hands it to the policy
limiter. `consume()` acquires that lock, reads the counter, writes the new
value back, then releases it in a `finally` before returning. Everything that
follows in `reveal()` (policy, authorization, audit, render) runs with no lock
held. A burst of concurrent clicks therefore serializes only the small read-
modify-write of the counter (each `consume()` waits its turn and blocks
briefly) and exactly the configured quota passes. Your whole backend
transaction is never serialized behind that lock: take the token, release the
lock, then do the work.

### 5.4 Authorization: gate every reveal

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

### 5.5 Audit: trace every attempt

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

### 5.6 The final component

With every layer in place, `reveal()` reads as a checkpoint chain. The browser
keeps seeing only the id and the name in its state; the email and the phone
are read server side and rendered for the single re-render that follows a
click. The whole chain is verified end to end in step 7.

## Step 6: stage 5, accept the Client object

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

The template becomes a slightly cleaner version of the one in step 4.2, with
`data-live-card` marking the card, the eye icons still rendered by `ux_icon()`
from the local `bi` set, and the contact details read from the hydrated
object:

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

## Step 7: the final files and the verify checklist

All final files are in `app/`:

- [app/src/Twig/Components/ClientCard.php](app/src/Twig/Components/ClientCard.php)
- [app/templates/components/ClientCard.html.twig](app/templates/components/ClientCard.html.twig)
- [app/src/Controller/LiveController.php](app/src/Controller/LiveController.php)
- [app/templates/index.html.twig](app/templates/index.html.twig)
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

## How each layer maps to a guarantee

| Layer | Added in | Package |
| --- | --- | --- |
| Value kept off the HTML | stage 1 (step 2) | none |
| Minimal client state | stages 3 and 5 (steps 4 and 6) | none |
| Same-origin protection | stage 3 (step 4) | spawned by the live recipe |
| Rate limit | layer 5.2 | `symfony/rate-limiter` |
| Lock | layer 5.3 | `symfony/lock` |
| Authorization | layer 5.4 | none |
| Audit trail | layer 5.5 | none |
| Inline errors | layers 5.2 and 5.4 | none |

## Production notes

- Use a shared cache pool and a shared lock store (Redis or DBAL) so the quotas
  survive restarts and multiple instances.
- Key the limiter on the authenticated user instead of a visitor cookie.
- Replace `isGranted()` with a real policy: roles or voters on the Security
  component, possibly field aware.
- Add a lifetime to what the client holds. The checksum stops tampering but
  not replay: it carries no expiry, so a captured props blob stays valid. An
  `expiresAt` LiveProp, verified inside `reveal()`, reproduces the expiry of a
  signed URL; the rate limiter bounds the replay in the meantime.
- Tradeoff: hiding then re-revealing a card consumes another token, because
  every action is a fresh server round trip. Prefetching or caching the value
  client side would bypass the audit each reveal is meant to produce.

## Where the code lives

The full application, at its final state, is in `app/`. Copy each file from its
linked source, or run the whole `app/` as a reference, and re-verify with step
7.
