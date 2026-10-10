<?php

declare(strict_types=1);

namespace Nsmeele\InertiaSymfony;

use Nsmeele\InertiaSymfony\EventListener\ErrorPageListener;
use Nsmeele\InertiaSymfony\EventListener\InertiaListener;
use Nsmeele\InertiaSymfony\EventListener\ValidationExceptionListener;
use Nsmeele\InertiaSymfony\Twig\InertiaExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Translation\Translator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class InertiaSymfonyBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('locale')
                    ->defaultValue('en_GB')
                    ->info('Default locale, which also decides the language of validation messages. An explicit framework.default_locale takes precedence.')
                ->end()
                ->scalarNode('root_view')->defaultValue('@InertiaSymfony/app.html.twig')->end()
                ->scalarNode('version')->defaultNull()->info('Asset version; defaults to a hash of the Vite manifest.')->end()
                ->scalarNode('manifest_path')->defaultValue('%kernel.project_dir%/public/build/.vite/manifest.json')->end()
                ->booleanNode('encrypt_history')->defaultFalse()->end()
                ->scalarNode('error_component')->defaultNull()->info('Page component rendered for HTTP errors on Inertia requests, outside debug mode.')->end()
            ->end();
    }

    /**
     * @param array{locale: string, root_view: string, version: ?string, manifest_path: string, encrypt_history: bool, error_component: ?string} $config
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
                $config['manifest_path'],
                $config['encrypt_history'],
            ])
            ->autoconfigure()
            ->public();

        $services->set(InertiaListener::class)
            ->args([service(Inertia::class)])
            ->autoconfigure();

        $services->set(ValidationExceptionListener::class)
            ->args([service(Inertia::class)])
            ->autoconfigure();

        if ($config['error_component'] !== null) {
            $services->set(ErrorPageListener::class)
                ->args([service(Inertia::class), $config['error_component'], param('kernel.debug')])
                ->autoconfigure();
        }

        $services->set(InertiaExtension::class)->tag('twig.extension');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $locale = 'en_GB';
        foreach ($builder->getExtensionConfig('inertia_symfony') as $config) {
            $locale = $config['locale'] ?? $locale;
        }

        // Prepended config has the lowest priority, so the application can always override it.
        $framework = ['default_locale' => $locale];
        if (class_exists(Translator::class)) {
            // Validation messages without a translation for the locale fall back to English.
            $framework['translator'] = ['fallbacks' => ['en']];
        }
        $builder->prependExtensionConfig('framework', $framework);

        $builder->prependExtensionConfig('twig', [
            'paths' => [__DIR__.'/../templates' => 'InertiaSymfony'],
        ]);
    }
}
