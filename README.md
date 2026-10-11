# Inertia Symfony

A server-side adapter for [Inertia.js](https://inertiajs.com) v3 on Symfony 8.1+.

It works with the official frontend adapters (`@inertiajs/react`, `@inertiajs/vue3`, `@inertiajs/svelte`) unchanged. This bundle only implements the server side of the protocol.

## Requirements

- PHP 8.4 or later
- Symfony 8.1 or later
- Twig

## Installation

The package is not on Packagist yet, so add the repository to your application's `composer.json` first:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/nsmeele/inertia-symfony" }
]
```

Then require it:

```bash
composer require nsmeele/inertia-symfony:^0.1
```

If you do not use Symfony Flex, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Nsmeele\InertiaSymfony\InertiaSymfonyBundle::class => ['all' => true],
];
```

Optional packages, depending on what you use:

| Package | Needed for |
|---|---|
| `symfony/validator` and `symfony/serializer` | `#[MapRequestPayload]`, `#[MapQueryString]` and `#[MapUploadedFile]` validation errors |
| `symfony/translation` | validation messages in locales other than English |
| `symfony/form` | `ValidationErrors::fromForm()` |

## Frontend

Install the official adapter for your framework, for example:

```bash
npm install @inertiajs/react
```

## Quick start

### Root template

The bundle ships a minimal root template, `@InertiaSymfony/app.html.twig`. For your own layout and Vite tags, create a template and point `root_view` at it:

```twig
{# templates/app.html.twig #}
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {# your Vite tags here #}
</head>
<body>
    {{ inertia(page) }}
</body>
</html>
```

```yaml
# config/packages/inertia_symfony.yaml
inertia_symfony:
    root_view: app.html.twig
```

`inertia(page)` outputs the page data and the mount element:

```html
<script data-page="app" type="application/json">{...}</script><div id="app"></div>
```

To use another element id, pass it as the second argument: `{{ inertia(page, 'root') }}`. It must match the `id` option of `createInertiaApp()`.

### Controller

```php
use Nsmeele\InertiaSymfony\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class UserController
{
    public function __construct(private Inertia $inertia)
    {
    }

    #[Route('/users')]
    public function index(): Response
    {
        return $this->inertia->render('Users/Index', [
            'users' => $this->users->findAll(),
        ]);
    }
}
```

On a full page load the response is HTML. On Inertia visits it is JSON with the `X-Inertia` header.

Redirects after `PUT`, `PATCH` and `DELETE` are converted from 302 to 303, and a changed asset version makes the server answer 409 so the client reloads.

## Props

```php
$this->inertia->render('Users/Index', [
    'users' => fn () => $this->users->findAll(),              // lazy, resolved only when needed
    'flash' => Inertia::always($flash),                       // included in every response, even partial reloads
    'stats' => Inertia::optional(fn () => $this->stats()),    // only when requested explicitly
    'comments' => Inertia::defer(fn () => $this->comments(), 'sidebar'), // loaded after the first render, optionally grouped
    'feed' => Inertia::merge($items),                         // appended on the client; Inertia::merge($items, deep: true) merges deeply
]);
```

Partial reloads (`only`, `except`) and `reset` are handled automatically.

### Shared props

Props shared with every page are set with `share()`. Do this in your own listener, so the application decides what is shared:

```php
use Nsmeele\InertiaSymfony\Inertia;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final readonly class ShareInertiaPropsListener
{
    public function __construct(private Inertia $inertia, private Security $security)
    {
    }

    #[AsEventListener]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->inertia->share('auth', [
            'user' => $this->security->getUser()?->getUserIdentifier(),
        ]);
    }
}
```

Shared state is reset between requests, so this is safe in long-running runtimes such as FrankenPHP and RoadRunner.

## Validation errors

When a controller argument with `#[MapRequestPayload]`, `#[MapQueryString]` or `#[MapUploadedFile]` fails validation on an Inertia request, the bundle:

1. flashes the errors in the session,
2. redirects back with a 303 (to the `Referer`, only when it is on the same host), and
3. adds them to the `errors` prop of the next page.

```php
#[Route('/users', methods: ['POST'])]
public function store(#[MapRequestPayload] CreateUser $user): Response
{
    // ...
}
```

`errors` is a flat map with the first message per field. Paths use dot notation (`items.0.name`), and errors without a field are under `_global`. The `errors` prop is always present, as `{}` when there are none.

If the client sends an error bag (`X-Inertia-Error-Bag`), the errors are nested under its name: `errors.createUser.name`.

This needs a session. Requests that are not Inertia requests keep Symfony's default 422 response.

### Your own errors and Symfony forms

```php
use Nsmeele\InertiaSymfony\Validation\ValidationErrors;

$this->inertia->flashErrors(['email' => 'This email address is already registered.']);

// From a submitted Symfony form (requires symfony/form):
$this->inertia->flashErrors(ValidationErrors::fromForm($form));
```

## Locale and validation messages

The default locale is `en_GB`, so validation messages are in British English by default. Change it with:

```yaml
inertia_symfony:
    locale: nl_NL
```

The bundle sets this as `framework.default_locale`, so a `default_locale` you configure under `framework` always takes precedence. With `symfony/translation` installed, messages without a translation for the locale fall back to English.

To provide your own translations, add standard Symfony translation files, for example:

```yaml
# translations/validators.nl_NL.yaml
This value should not be blank.: This field is required.
```

## Error pages

Render HTTP errors on Inertia requests as a page of your own:

```yaml
inertia_symfony:
    error_component: Error
```

The component receives a `status` prop. Exception messages are never included. Full page loads keep Symfony's server-rendered error pages, and the error page is only used outside debug mode.

## History encryption

```php
$this->inertia->encryptHistory();   // encrypt this page in the browser history
$this->inertia->clearHistory();     // clear the encrypted history on the next page, e.g. after logout
```

To encrypt every page, set `encrypt_history: true`.

## Asset version

Inertia reloads the page when the asset version changes. By default the version is a hash of the Vite manifest at `%kernel.project_dir%/public/build/.vite/manifest.json`. Use another path, or set the version yourself:

```yaml
inertia_symfony:
    manifest_path: '%kernel.project_dir%/public/build/manifest.json'
    # version: '1.2.3'
```

## Redirecting to an external URL

```php
return $this->inertia->location('https://example.com');
```

This answers 409 with `X-Inertia-Location` for Inertia requests and a normal redirect otherwise.

## Configuration reference

```yaml
inertia_symfony:
    locale: en_GB
    root_view: '@InertiaSymfony/app.html.twig'
    version: null
    manifest_path: '%kernel.project_dir%/public/build/.vite/manifest.json'
    encrypt_history: false
    error_component: null
```

## Not implemented yet

- Server-side rendering (SSR)
- Once props and scroll props
- Test helpers

## Licence

MIT
