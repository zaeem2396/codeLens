<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony Bundle for CodeLens.
 *
 * Integrates CodeLens with Symfony applications,
 * providing automatic configuration and registration.
 */
class CodeLensBundle extends Bundle
{
    /**
     * Get the bundle extension.
     */
    public function getContainerExtension(): DependencyInjection\CodeLensExtension
    {
        return new DependencyInjection\CodeLensExtension();
    }

    /**
     * Get the path to this bundle.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__ . '/../');
    }
}
