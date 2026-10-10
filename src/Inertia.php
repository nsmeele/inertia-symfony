<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony;

use Nsmeele\InertiaSymfony\Props\AlwaysProp;
use Nsmeele\InertiaSymfony\Props\DeferProp;
use Nsmeele\InertiaSymfony\Props\MergeProp;
use Nsmeele\InertiaSymfony\Props\OptionalProp;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Entry point for controllers: build Inertia page responses and share props.
 */
class Inertia
{
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

        [$resolved, $metadata] = $this->resolver->resolve($request, $component, [...$this->shared, ...$props]);

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
