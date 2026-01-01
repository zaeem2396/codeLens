<?php

declare(strict_types=1);

namespace CodeLens\Core\Contracts;

/**
 * Interface for framework-specific adapters.
 *
 * Each supported framework (Laravel, Symfony) must implement
 * this interface to integrate with CodeLens.
 */
interface FrameworkAdapterInterface
{
    /**
     * Get the name of the framework.
     */
    public function getFrameworkName(): string;

    /**
     * Get the version of the framework.
     */
    public function getFrameworkVersion(): string;

    /**
     * Get the current environment (e.g., 'local', 'staging', 'production').
     */
    public function getCurrentEnvironment(): string;

    /**
     * Get the base path of the application.
     */
    public function getBasePath(): string;

    /**
     * Get the path to the application source code.
     */
    public function getSourcePath(): string;

    /**
     * Get the path where CodeLens should store its cache/data.
     */
    public function getStoragePath(): string;

    /**
     * Get framework-specific configuration value.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed;

    /**
     * Check if the framework is in debug mode.
     */
    public function isDebugMode(): bool;

    /**
     * Get the registered routes (for usage analysis).
     * Returns an array of route information.
     */
    public function getRoutes(): array;

    /**
     * Get registered service bindings (for dependency analysis).
     * Returns an array of service/binding information.
     */
    public function getServiceBindings(): array;
}
