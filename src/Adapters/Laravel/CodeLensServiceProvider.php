<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Laravel;

use CodeLens\Core\CodeLens;
use CodeLens\Core\Config\Configuration;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel Service Provider for CodeLens.
 * 
 * Integrates CodeLens with Laravel applications,
 * providing automatic configuration and registration.
 */
class CodeLensServiceProvider extends ServiceProvider
{
    /**
     * Register CodeLens services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/codelens.php',
            'codelens'
        );

        $this->app->singleton(Configuration::class, function ($app) {
            return Configuration::fromArray($app['config']->get('codelens', []));
        });

        $this->app->singleton(LaravelAdapter::class, function ($app) {
            return new LaravelAdapter($app);
        });

        $this->app->singleton(CodeLens::class, function ($app) {
            $configuration = $app->make(Configuration::class);
            $adapter = $app->make(LaravelAdapter::class);
            
            return CodeLens::getInstance($configuration)->initialize($adapter);
        });

        $this->app->alias(CodeLens::class, 'codelens');
    }

    /**
     * Bootstrap CodeLens services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            $this->registerCommands();
        }

        // Only proceed if CodeLens is enabled for this environment
        $codeLens = $this->app->make(CodeLens::class);
        
        if (!$codeLens->isEnabled()) {
            return;
        }

        // Future: Register routes for UI
        // Future: Register views
    }

    /**
     * Publish the configuration file.
     */
    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/config/codelens.php' => config_path('codelens.php'),
        ], 'codelens-config');
    }

    /**
     * Register console commands.
     */
    private function registerCommands(): void
    {
        $this->commands([
            Commands\ScanCommand::class,
        ]);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            CodeLens::class,
            Configuration::class,
            LaravelAdapter::class,
            'codelens',
        ];
    }
}

