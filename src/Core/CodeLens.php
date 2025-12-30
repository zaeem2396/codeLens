<?php

declare(strict_types=1);

namespace CodeLens\Core;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Contracts\FrameworkAdapterInterface;

/**
 * CodeLens - Static Code Intelligence Tool
 *
 * The main entry point for the CodeLens system.
 * This class orchestrates all code analysis operations
 * while remaining framework-agnostic.
 */
final class CodeLens
{
    private static ?self $instance = null;

    private Configuration $configuration;
    private ?FrameworkAdapterInterface $adapter = null;
    private bool $initialized = false;

    private function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Get or create the CodeLens instance.
     */
    public static function getInstance(?Configuration $configuration = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($configuration ?? new Configuration());
        }

        return self::$instance;
    }

    /**
     * Reset the singleton instance (useful for testing).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Initialize CodeLens with a framework adapter.
     */
    public function initialize(FrameworkAdapterInterface $adapter): self
    {
        if ($this->initialized) {
            return $this;
        }

        $this->adapter = $adapter;
        $this->validateEnvironment();
        $this->initialized = true;

        return $this;
    }

    /**
     * Check if CodeLens has been initialized.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Check if CodeLens is enabled for the current environment.
     */
    public function isEnabled(): bool
    {
        if (! $this->initialized) {
            return false;
        }

        return $this->configuration->isEnabledForEnvironment(
            $this->adapter->getCurrentEnvironment(),
        );
    }

    /**
     * Get the current configuration.
     */
    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * Get the framework adapter.
     */
    public function getAdapter(): ?FrameworkAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Get the detected framework name.
     */
    public function getFrameworkName(): string
    {
        return $this->adapter?->getFrameworkName() ?? 'Unknown';
    }

    /**
     * Get the framework version.
     */
    public function getFrameworkVersion(): string
    {
        return $this->adapter?->getFrameworkVersion() ?? 'Unknown';
    }

    /**
     * Validate that the current environment is allowed.
     */
    private function validateEnvironment(): void
    {
        $currentEnv = $this->adapter->getCurrentEnvironment();

        if (! $this->configuration->isEnabledForEnvironment($currentEnv)) {
            // Silent disable - we don't throw, we just won't activate
            $this->initialized = false;
        }
    }
}
