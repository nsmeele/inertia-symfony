<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony;

use Nsmeele\InertiaSymfony\Props\AlwaysProp;
use Nsmeele\InertiaSymfony\Props\DeferProp;
use Nsmeele\InertiaSymfony\Props\MergeProp;
use Nsmeele\InertiaSymfony\Props\OptionalProp;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;

/**
 * Entry point for controllers: build Inertia page responses and share props.
 */
class Inertia implements ResetInterface
{
    private const string ERRORS_FLASH = 'inertia.errors';
    private const string CLEAR_HISTORY_FLASH = 'inertia.clear_history';

    /** @var array<string, mixed> */
    private array $shared = [];

    private ?bool $encryptHistory = null;

    private ?string $resolvedVersion = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Environment $twig,
        private readonly PropsResolver $resolver,
        private readonly string $rootView = 'inertia/app.html.twig',
        private readonly ?string $version = null,
        private readonly ?string $manifestPath = null,
        private readonly bool $defaultEncryptHistory = false,
    ) {
    }

    /**
     * @param array<string, mixed> $props
     */
    public function render(string $component, array $props = []): Response
    {
        $request = $this->requestStack->getCurrentRequest()
            ?? throw new \LogicException('Inertia::render() needs an active request.');

        $shared = ['errors' => new AlwaysProp(fn () => $this->pullErrors($request)), ...$this->shared];

        [$resolved, $metadata] = $this->resolver->resolve($request, $component, [...$shared, ...$props]);

        $page = [
            'component' => $component,
            'props' => (object) $resolved,
            'url' => $request->getRequestUri(),
            'version' => $this->getVersion(),
            ...$metadata,
        ];

        if ($this->encryptHistory ?? $this->defaultEncryptHistory) {
            $page['encryptHistory'] = true;
        }

        if ($this->pullClearHistory($request)) {
            $page['clearHistory'] = true;
        }

        if ($request->headers->get(Header::INERTIA) !== null) {
            return new JsonResponse($page, 200, [Header::INERTIA => 'true']);
        }

        return new Response($this->twig->render($this->rootView, ['page' => $page]));
    }

    public function share(string|array $key, mixed $value = null): void
    {
        $this->shared = is_array($key) ? [...$this->shared, ...$key] : [...$this->shared, $key => $value];
    }

    /**
     * Flash validation errors so the next rendered page gets them in its `errors` prop.
     * With an error bag the errors are nested under the bag name (X-Inertia-Error-Bag).
     *
     * @param array<string, string> $errors
     */
    public function flashErrors(array $errors, ?string $bag = null): void
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            throw new \LogicException('Flashing Inertia errors requires a session.');
        }

        $session->getFlashBag()->set(self::ERRORS_FLASH, [$bag !== null && $bag !== '' ? [$bag => $errors] : $errors]);
    }

    /**
     * Force a full page visit, e.g. for external redirects (409 for Inertia requests).
     */
    public function location(string $url): Response
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request?->headers->get(Header::INERTIA) !== null) {
            return new Response('', Response::HTTP_CONFLICT, [Header::LOCATION => $url]);
        }

        return new RedirectResponse($url);
    }

    private function pullClearHistory(Request $request): bool
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        return $session instanceof FlashBagAwareSessionInterface
            && $session->getFlashBag()->get(self::CLEAR_HISTORY_FLASH) !== [];
    }

    private function pullErrors(Request $request): array|\stdClass
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return new \stdClass();
        }

        return $session->getFlashBag()->get(self::ERRORS_FLASH)[0] ?? new \stdClass();
    }

    /**
     * Encrypt this page in the browser's history state (use before rendering).
     */
    public function encryptHistory(bool $encrypt = true): void
    {
        $this->encryptHistory = $encrypt;
    }

    /**
     * Drop the encrypted history of the current browser session on the next rendered page,
     * e.g. after logging out. Survives a redirect.
     */
    public function clearHistory(): void
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            throw new \LogicException('Clearing Inertia history requires a session.');
        }

        $session->getFlashBag()->set(self::CLEAR_HISTORY_FLASH, [true]);
    }

    /**
     * Asset version: the configured `version`, else a hash of the Vite manifest, else empty.
     */
    public function getVersion(): string
    {
        return $this->resolvedVersion ??= $this->version
            ?? ($this->manifestPath !== null && is_file($this->manifestPath) ? (string) md5_file($this->manifestPath) : '');
    }

    /**
     * Shared props live on the service, so clear them between requests in long-running runtimes.
     */
    public function reset(): void
    {
        $this->shared = [];
        $this->encryptHistory = null;
    }

    public static function always(mixed $value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    public static function optional(\Closure $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    public static function defer(\Closure $callback, string $group = 'default'): DeferProp
    {
        return new DeferProp($callback, $group);
    }

    public static function merge(mixed $value, bool $deep = false): MergeProp
    {
        return new MergeProp($value, $deep);
    }
}
