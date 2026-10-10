<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony\Tests\Fixtures;

use Nsmeele\InertiaSymfony\InertiaSymfonyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new InertiaSymfonyBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/inertia-symfony-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/inertia-symfony-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['test' => true, 'secret' => 'test', 'http_method_override' => false,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'validation' => ['enabled' => true],
            'serializer' => ['enabled' => true],
            'property_access' => ['enabled' => true],
            'property_info' => ['enabled' => true],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $container->extension('inertia_symfony', ['version' => 'v1']);
        $container->services()
            ->set(TestController::class)->autowire()->autoconfigure()->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(TestController::class, 'attribute');
    }
}
