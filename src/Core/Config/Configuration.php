<?php

declare(strict_types=1);

namespace CodeLens\Core\Config;

/**
 * Configuration handler for CodeLens.
 *
 * Manages all configuration options with sensible defaults
 * and environment-based restrictions.
 */
final class Configuration
{
    /**
     * Default configuration values.
     */
    private const DEFAULTS = [
        // Environments where CodeLens is enabled
        'enabled_environments' => ['local', 'development', 'dev', 'staging'],

        // Paths to scan (relative to source path)
        'scan_paths' => ['app', 'src'],

        // Paths to exclude from scanning
        'exclude_paths' => ['vendor', 'node_modules', 'storage', 'cache', 'var'],

        // File extensions to analyze
        'file_extensions' => ['php'],

        // Storage driver: 'json' or 'sqlite'
        'storage_driver' => 'json',

        // Cache directory name (relative to storage path)
        'cache_directory' => 'codelens',

        // Enable/disable the web UI
        'ui_enabled' => true,

        // Route prefix for the web UI
        'ui_route_prefix' => 'codelens',

        // Middleware to apply to UI routes
        'ui_middleware' => [],
    ];

    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::DEFAULTS, $config);
    }

    /**
     * Create configuration from an array.
     */
    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    /**
     * Get a configuration value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set a configuration value.
     */
    public function set(string $key, mixed $value): self
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * Check if CodeLens is enabled for a given environment.
     */
    public function isEnabledForEnvironment(string $environment): bool
    {
        $enabledEnvironments = $this->get('enabled_environments', []);

        return in_array(strtolower($environment), array_map('strtolower', $enabledEnvironments), true);
    }

    /**
     * Get paths to scan.
     */
    public function getScanPaths(): array
    {
        return $this->get('scan_paths', []);
    }

    /**
     * Get paths to exclude.
     */
    public function getExcludePaths(): array
    {
        return $this->get('exclude_paths', []);
    }

    /**
     * Get file extensions to analyze.
     */
    public function getFileExtensions(): array
    {
        return $this->get('file_extensions', []);
    }

    /**
     * Get the storage driver.
     */
    public function getStorageDriver(): string
    {
        return $this->get('storage_driver', 'json');
    }

    /**
     * Get the cache directory name.
     */
    public function getCacheDirectory(): string
    {
        return $this->get('cache_directory', 'codelens');
    }

    /**
     * Check if UI is enabled.
     */
    public function isUiEnabled(): bool
    {
        return (bool) $this->get('ui_enabled', true);
    }

    /**
     * Get UI route prefix.
     */
    public function getUiRoutePrefix(): string
    {
        return $this->get('ui_route_prefix', 'codelens');
    }

    /**
     * Get UI middleware.
     */
    public function getUiMiddleware(): array
    {
        return $this->get('ui_middleware', []);
    }

    /**
     * Get all configuration as array.
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Merge additional configuration.
     */
    public function merge(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
}
