<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\EventListener;

use Nsmeele\InertiaSymfony\Header;
use Nsmeele\InertiaSymfony\Inertia;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies the protocol rules that wrap every response:
 * Vary header, asset version mismatch (409) and 302 -> 303 for PUT/PATCH/DELETE.
 */
final readonly class InertiaListener
{
    public function __construct(private Inertia $inertia)
    {
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $response->headers->set('Vary', Header::INERTIA, false);

        if ($request->headers->get(Header::INERTIA) === null) {
            return;
        }

        if ($request->isMethod('GET')
            && $request->headers->get(Header::VERSION, '') !== $this->inertia->getVersion()) {
            $event->setResponse(new Response('', Response::HTTP_CONFLICT, [
                Header::LOCATION => $request->getUri(),
                Header::VERSION => $this->inertia->getVersion(),
                'Vary' => Header::INERTIA,
            ]));

            return;
        }

        if ($response->getStatusCode() === Response::HTTP_FOUND
            && in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'], true)) {
            $response->setStatusCode(Response::HTTP_SEE_OTHER);
        }
    }
}
