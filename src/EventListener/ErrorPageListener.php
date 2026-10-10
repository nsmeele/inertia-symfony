<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\EventListener;

use Nsmeele\InertiaSymfony\Header;
use Nsmeele\InertiaSymfony\Inertia;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Renders HTTP errors as an Inertia page for Inertia requests only, so error screens use the app's own layout.
 * Full page loads keep Symfony's server-rendered error pages, which work without JavaScript.
 * Registered only when `error_component` is configured; the debug error page stays in dev.
 */
final readonly class ErrorPageListener
{
    public function __construct(
        private Inertia $inertia,
        private string $component,
        private bool $debug,
    ) {
    }

    // Lower than the default so validation failures are turned into redirects first.
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: -10)]
    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if ($this->debug || !$event->isMainRequest() || $request->headers->get(Header::INERTIA) === null) {
            return;
        }

        $exception = $event->getThrowable();
        $http = $exception instanceof HttpExceptionInterface ? $exception : null;
        $status = $http?->getStatusCode() ?? 500;

        // Only the status code goes into the page data: exception messages are not meant for visitors.
        $response = $this->inertia->render($this->component, ['status' => $status]);
        $response->setStatusCode($status);
        $response->headers->add($http?->getHeaders() ?? []);

        $event->setResponse($response);
    }
}
