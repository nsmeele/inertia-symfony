<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\Tests;

use Nsmeele\InertiaSymfony\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class InertiaTest extends TestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $this->client = new KernelBrowser($kernel);
    }

    /** @return array<string, mixed> */
    private function visit(string $method, string $uri, array $headers = []): array
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $this->client->request($method, $uri, server: $server);

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    public function testFirstVisitRendersRootViewWithPageObject(): void
    {
        $this->client->request('GET', '/users');

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-page=', (string) $response->getContent());
        self::assertStringContainsString('Users/Index', html_entity_decode((string) $response->getContent()));
        self::assertSame('X-Inertia', $response->headers->get('Vary'));
    }

    public function testInertiaRequestReturnsJsonPage(): void
    {
        $page = $this->visit('GET', '/users', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1']);

        $response = $this->client->getResponse();
        self::assertSame('true', $response->headers->get('X-Inertia'));
        self::assertSame('Users/Index', $page['component']);
        self::assertSame('/users', $page['url']);
        self::assertSame('v1', $page['version']);
        self::assertSame(['alice', 'bob'], $page['props']['users']);
        self::assertSame('demo', $page['props']['app']);
    }

    public function testFirstLoadExcludesOptionalAndDeferredProps(): void
    {
        $page = $this->visit('GET', '/users', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1']);

        self::assertArrayNotHasKey('stats', $page['props']);
        self::assertArrayNotHasKey('comments', $page['props']);
        self::assertSame(['sidebar' => ['comments']], $page['deferredProps']);
        self::assertSame(['feed'], $page['mergeProps']);
    }

    public function testPartialReloadOnlyReturnsRequestedPropsAndAlwaysProps(): void
    {
        $page = $this->visit('GET', '/users', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => 'v1',
            'X-Inertia-Partial-Component' => 'Users/Index',
            'X-Inertia-Partial-Data' => 'comments,stats',
        ]);

        self::assertSame(['nice'], $page['props']['comments']);
        self::assertSame(['total' => 2], $page['props']['stats']);
        self::assertSame('hello', $page['props']['flash']);
        self::assertArrayNotHasKey('users', $page['props']);
        self::assertArrayNotHasKey('deferredProps', $page);
    }

    public function testPartialReloadSupportsExcept(): void
    {
        $page = $this->visit('GET', '/users', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => 'v1',
            'X-Inertia-Partial-Component' => 'Users/Index',
            'X-Inertia-Partial-Except' => 'users',
        ]);

        self::assertArrayNotHasKey('users', $page['props']);
        self::assertSame(2, $page['props']['count']);
    }

    public function testPartialHeadersAreIgnoredForOtherComponents(): void
    {
        $page = $this->visit('GET', '/users', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => 'v1',
            'X-Inertia-Partial-Component' => 'Other/Page',
            'X-Inertia-Partial-Data' => 'count',
        ]);

        self::assertArrayHasKey('users', $page['props']);
    }

    public function testVersionMismatchTriggersFullReload(): void
    {
        $this->client->request('GET', '/users', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => 'stale',
        ]);

        $response = $this->client->getResponse();
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('http://localhost/users', $response->headers->get('X-Inertia-Location'));
    }

    public function testRedirectAfterPutBecomes303(): void
    {
        $this->client->request('PUT', '/users/1', server: ['HTTP_X_INERTIA' => 'true']);

        self::assertSame(303, $this->client->getResponse()->getStatusCode());
    }

    public function testLocationReturns409ForInertiaRequests(): void
    {
        $this->client->request('GET', '/external', server: [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => 'v1',
        ]);

        $response = $this->client->getResponse();
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('https://example.com', $response->headers->get('X-Inertia-Location'));
    }
}
