<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\Tests\Fixtures;

use Nsmeele\InertiaSymfony\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class TestController
{
    public function __construct(private Inertia $inertia)
    {
    }

    #[Route('/users', methods: ['GET'])]
    public function index(): Response
    {
        $this->inertia->share('app', 'demo');

        return $this->inertia->render('Users/Index', [
            'users' => fn () => ['alice', 'bob'],
            'count' => 2,
            'flash' => Inertia::always('hello'),
            'stats' => Inertia::optional(fn () => ['total' => 2]),
            'comments' => Inertia::defer(fn () => ['nice'], 'sidebar'),
            'feed' => Inertia::merge([1, 2]),
        ]);
    }

    #[Route('/users', methods: ['POST'])]
    public function store(#[MapRequestPayload] CreateUser $user): Response
    {
        return new RedirectResponse('/users');
    }

    #[Route('/users/{id}', methods: ['PUT'])]
    public function update(): Response
    {
        return new RedirectResponse('/users');
    }

    #[Route('/external', methods: ['GET'])]
    public function external(): Response
    {
        return $this->inertia->location('https://example.com');
    }
}
