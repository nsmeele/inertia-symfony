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
use Twig\Environment;

/**
 * Entry point for controllers: build Inertia page responses and share props.
 */
class Inertia
{
    private const string ERRORS_FLASH = 'inertia.errors';

    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Environment $twig,
        private readonly PropsResolver $resolver,
        private readonly string $rootView = 'inertia/app.html.twig',
        private readonly ?string $version = null,
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

    private function pullErrors(Request $request): array|\stdClass
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return new \stdClass();
        }

        return $session->getFlashBag()->get(self::ERRORS_FLASH)[0] ?? new \stdClass();
    }

    public function getVersion(): string
    {
        return $this->version ?? '';
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
