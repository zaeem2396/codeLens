<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Symfony;

use CodeLens\Core\Contracts\FrameworkAdapterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Symfony-specific adapter for CodeLens.
 * 
 * Provides Symfony-specific implementations for
 * framework detection, routing, and service container access.
 */
class SymfonyAdapter implements FrameworkAdapterInterface
{
    private KernelInterface $kernel;
    private RouterInterface $router;
    private ContainerInterface $container;

    public function __construct(
        KernelInterface $kernel,
        RouterInterface $router,
        ContainerInterface $container
    ) {
        $this->kernel = $kernel;
        $this->router = $router;
        $this->container = $container;
    }

    /**
     * Get the framework name.
     */
    public function getFrameworkName(): string
    {
        return 'Symfony';
    }

    /**
     * Get the Symfony version.
     */
    public function getFrameworkVersion(): string
    {
        return \Symfony\Component\HttpKernel\Kernel::VERSION;
    }

    /**
     * Get the current environment.
     */
    public function getCurrentEnvironment(): string
    {
        return $this->kernel->getEnvironment();
    }

    /**
     * Get the application base path (project root).
     */
    public function getBasePath(): string
    {
        return $this->kernel->getProjectDir();
    }

    /**
     * Get the path to the application source code.
     */
    public function getSourcePath(): string
    {
        return $this->kernel->getProjectDir() . '/src';
    }

    /**
     * Get the storage path for CodeLens data.
     */
    public function getStoragePath(): string
    {
        return $this->kernel->getCacheDir() . '/codelens';
    }

    /**
     * Get a configuration value.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        $parameterName = 'codelens.' . $key;
        
        if ($this->container->hasParameter($parameterName)) {
            return $this->container->getParameter($parameterName);
        }

        return $default;
    }

    /**
     * Check if the application is in debug mode.
     */
    public function isDebugMode(): bool
    {
        return $this->kernel->isDebug();
    }

    /**
     * Get registered routes.
     * 
     * Returns an array of route information for usage analysis.
     */
    public function getRoutes(): array
    {
        $routes = [];
        $routeCollection = $this->router->getRouteCollection();

        foreach ($routeCollection->all() as $name => $route) {
            $defaults = $route->getDefaults();
            $controller = $defaults['_controller'] ?? null;

            $routes[] = [
                'name' => $name,
                'path' => $route->getPath(),
                'methods' => $route->getMethods() ?: ['ANY'],
                'controller' => $controller,
                'requirements' => $route->getRequirements(),
            ];
        }

        return $routes;
    }

    /**
     * Get service container bindings.
     * 
     * Returns an array of service binding information.
     * Note: In Symfony, we can only introspect public services
     * or those explicitly tagged.
     */
    public function getServiceBindings(): array
    {
        $bindings = [];

        // Get service IDs (only public services are accessible this way)
        // In production, the container is compiled and private services
        // are not directly accessible. This is a limitation we document.
        $serviceIds = $this->container->getServiceIds();

        foreach ($serviceIds as $serviceId) {
            // Skip internal Symfony services
            if (str_starts_with($serviceId, '.')) {
                continue;
            }

            $bindings[] = [
                'id' => $serviceId,
                'class' => $this->resolveServiceClass($serviceId),
                'public' => true, // Only public services are listed
            ];
        }

        return $bindings;
    }

    /**
     * Attempt to resolve the class of a service.
     */
    private function resolveServiceClass(string $serviceId): ?string
    {
        try {
            if ($this->container->has($serviceId)) {
                $service = $this->container->get($serviceId);
                if (is_object($service)) {
                    return get_class($service);
                }
            }
        } catch (\Throwable) {
            // Service might not be instantiable at this point
        }

        return null;
    }
}

