<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\EventListener;

use Nsmeele\InertiaSymfony\Header;
use Nsmeele\InertiaSymfony\Inertia;
use Nsmeele\InertiaSymfony\Validation\ValidationErrors;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Turns validation failures from #[MapRequestPayload], #[MapQueryString] and
 * #[MapUploadedFile] into the Inertia flow: flash the errors in the session and
 * redirect back, so the next page render receives them in the `errors` prop.
 */
final readonly class ValidationExceptionListener
{
    public function __construct(private Inertia $inertia)
    {
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION)]
    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->headers->get(Header::INERTIA) === null || !$request->hasSession()) {
            return;
        }

        $failure = null;
        for ($e = $event->getThrowable(); $e !== null; $e = $e->getPrevious()) {
            if ($e instanceof ValidationFailedException) {
                $failure = $e;
                break;
            }
        }

        if ($failure === null) {
            return;
        }

        $this->inertia->flashErrors(
            ValidationErrors::fromViolations($failure->getViolations()),
            $request->headers->get(Header::ERROR_BAG),
        );

        $event->setResponse(new RedirectResponse(
            $request->headers->get('Referer') ?: $request->getUri(),
            Response::HTTP_SEE_OTHER,
        ));
    }
}
