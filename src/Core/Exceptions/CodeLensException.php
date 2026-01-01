<?php

declare(strict_types=1);

namespace CodeLens\Core\Exceptions;

use Exception;

/**
 * Base exception for all CodeLens errors.
 */
class CodeLensException extends Exception
{
    /**
     * Create an exception for when CodeLens is used in a disabled environment.
     */
    public static function disabledEnvironment(string $environment): self
    {
        return new self(
            "CodeLens is not enabled for the '{$environment}' environment. " .
            "Check your configuration to enable it for this environment.",
        );
    }

    /**
     * Create an exception for when no adapter is configured.
     */
    public static function noAdapter(): self
    {
        return new self(
            "No framework adapter has been configured. " .
            "Please ensure CodeLens is properly installed in your framework.",
        );
    }

    /**
     * Create an exception for configuration errors.
     */
    public static function configurationError(string $message): self
    {
        return new self("Configuration error: {$message}");
    }
}
