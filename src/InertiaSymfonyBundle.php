<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony;

use Nsmeele\InertiaSymfony\EventListener\InertiaListener;
use Nsmeele\InertiaSymfony\Twig\InertiaExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class InertiaSymfonyBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('root_view')->defaultValue('@InertiaSymfony/app.html.twig')->end()
                ->scalarNode('version')->defaultNull()->end()
            ->end();
    }

    /**
     * @param array{root_view: string, version: ?string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(PropsResolver::class);

        $services->set(Inertia::class)
            ->args([
                service('request_stack'),
                service('twig'),
                service(PropsResolver::class),
                $config['root_view'],
                $config['version'],
            ])
            ->public();

        $services->set(InertiaListener::class)
            ->args([service(Inertia::class)])
            ->autoconfigure();

        $services->set(InertiaExtension::class)->tag('twig.extension');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('twig', [
            'paths' => [__DIR__.'/../templates' => 'InertiaSymfony'],
        ]);
    }
}
