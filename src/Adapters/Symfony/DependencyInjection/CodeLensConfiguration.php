<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration definition for CodeLens Symfony Bundle.
 *
 * Defines the structure and validation rules for
 * the codelens.yaml configuration file.
 */
class CodeLensConfiguration implements ConfigurationInterface
{
    /**
     * Get the configuration tree builder.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('codelens');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('enabled_environments')
                    ->defaultValue(['dev', 'staging'])
                    ->scalarPrototype()->end()
                    ->info('Environments where CodeLens is active')
                ->end()

                ->arrayNode('scan_paths')
                    ->defaultValue(['src'])
                    ->scalarPrototype()->end()
                    ->info('Directories to scan for PHP files')
                ->end()

                ->arrayNode('exclude_paths')
                    ->defaultValue(['vendor', 'var', 'node_modules', 'public'])
                    ->scalarPrototype()->end()
                    ->info('Directories to exclude from scanning')
                ->end()

                ->arrayNode('file_extensions')
                    ->defaultValue(['php'])
                    ->scalarPrototype()->end()
                    ->info('File extensions to analyze')
                ->end()

                ->enumNode('storage_driver')
                    ->values(['json', 'sqlite'])
                    ->defaultValue('json')
                    ->info('Storage driver for scan results')
                ->end()

                ->scalarNode('cache_directory')
                    ->defaultValue('codelens')
                    ->info('Directory name for CodeLens cache')
                ->end()

                ->booleanNode('ui_enabled')
                    ->defaultTrue()
                    ->info('Enable the web UI')
                ->end()

                ->scalarNode('ui_route_prefix')
                    ->defaultValue('codelens')
                    ->info('Route prefix for the web UI')
                ->end()

                ->arrayNode('ui_middleware')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->info('Middleware to apply to UI routes')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
