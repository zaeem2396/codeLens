<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Laravel;

use CodeLens\Core\Contracts\FrameworkAdapterInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;

/**
 * Laravel-specific adapter for CodeLens.
 * 
 * Provides Laravel-specific implementations for
 * framework detection, routing, and service container access.
 */
class LaravelAdapter implements FrameworkAdapterInterface
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get the framework name.
     */
    public function getFrameworkName(): string
    {
        return 'Laravel';
    }

    /**
     * Get the Laravel version.
     */
    public function getFrameworkVersion(): string
    {
        return $this->app->version();
    }

    /**
     * Get the current environment.
     */
    public function getCurrentEnvironment(): string
    {
        return $this->app->environment();
    }

    /**
     * Get the application base path.
     */
    public function getBasePath(): string
    {
        return $this->app->basePath();
    }

    /**
     * Get the path to the application source code.
     */
    public function getSourcePath(): string
    {
        return $this->app->path();
    }

    /**
     * Get the storage path for CodeLens data.
     */
    public function getStoragePath(): string
    {
        return $this->app->storagePath('framework/codelens');
    }

    /**
     * Get a configuration value.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->app['config']->get("codelens.{$key}", $default);
    }

    /**
     * Check if the application is in debug mode.
     */
    public function isDebugMode(): bool
    {
        return (bool) $this->app['config']->get('app.debug', false);
    }

    /**
     * Get registered routes.
     * 
     * Returns an array of route information for usage analysis.
     */
    public function getRoutes(): array
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $routes = [];

        foreach ($router->getRoutes() as $route) {
            $action = $route->getAction();
            
            $routes[] = [
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'name' => $route->getName(),
                'controller' => $action['controller'] ?? null,
                'action' => $action['uses'] ?? null,
                'middleware' => $route->middleware(),
            ];
        }

        return $routes;
    }

    /**
     * Get service container bindings.
     * 
     * Returns an array of service binding information.
     */
    public function getServiceBindings(): array
    {
        $bindings = [];

        foreach ($this->app->getBindings() as $abstract => $binding) {
            $bindings[] = [
                'abstract' => $abstract,
                'concrete' => $this->resolveConcreteType($binding),
                'shared' => $binding['shared'] ?? false,
            ];
        }

        return $bindings;
    }

    /**
     * Attempt to resolve the concrete type from a binding.
     */
    private function resolveConcreteType(array $binding): ?string
    {
        $concrete = $binding['concrete'] ?? null;

        if ($concrete === null) {
            return null;
        }

        if (is_string($concrete)) {
            return $concrete;
        }

        if ($concrete instanceof \Closure) {
            return 'Closure';
        }

        return get_class($concrete);
    }
}

