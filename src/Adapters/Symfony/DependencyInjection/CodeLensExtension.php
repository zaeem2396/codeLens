<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Symfony\DependencyInjection;

use CodeLens\Adapters\Symfony\SymfonyAdapter;
use CodeLens\Core\CodeLens;
use CodeLens\Core\Config\Configuration;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Dependency Injection Extension for CodeLens Symfony Bundle.
 * 
 * Handles the loading and configuration of CodeLens services
 * within the Symfony dependency injection container.
 */
class CodeLensExtension extends Extension
{
    /**
     * Load the CodeLens configuration and services.
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new CodeLensConfiguration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store configuration parameters
        $container->setParameter('codelens.enabled_environments', $config['enabled_environments']);
        $container->setParameter('codelens.scan_paths', $config['scan_paths']);
        $container->setParameter('codelens.exclude_paths', $config['exclude_paths']);
        $container->setParameter('codelens.file_extensions', $config['file_extensions']);
        $container->setParameter('codelens.storage_driver', $config['storage_driver']);
        $container->setParameter('codelens.cache_directory', $config['cache_directory']);
        $container->setParameter('codelens.ui_enabled', $config['ui_enabled']);
        $container->setParameter('codelens.ui_route_prefix', $config['ui_route_prefix']);
        $container->setParameter('codelens.ui_middleware', $config['ui_middleware']);

        // Register Configuration service
        $configDefinition = new Definition(Configuration::class);
        $configDefinition->setArguments([$config]);
        $container->setDefinition(Configuration::class, $configDefinition);
        $container->setAlias('codelens.configuration', Configuration::class);

        // Register SymfonyAdapter service
        $adapterDefinition = new Definition(SymfonyAdapter::class);
        $adapterDefinition->setArguments([
            new Reference('kernel'),
            new Reference('router'),
            new Reference('service_container'),
        ]);
        $container->setDefinition(SymfonyAdapter::class, $adapterDefinition);
        $container->setAlias('codelens.adapter', SymfonyAdapter::class);

        // Register CodeLens main service
        $codeLensDefinition = new Definition(CodeLens::class);
        $codeLensDefinition->setFactory([CodeLens::class, 'getInstance']);
        $codeLensDefinition->setArguments([new Reference(Configuration::class)]);
        $codeLensDefinition->addMethodCall('initialize', [new Reference(SymfonyAdapter::class)]);
        $container->setDefinition(CodeLens::class, $codeLensDefinition);
        $container->setAlias('codelens', CodeLens::class);
    }

    /**
     * Get the extension alias.
     */
    public function getAlias(): string
    {
        return 'codelens';
    }
}

