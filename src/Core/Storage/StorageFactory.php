<?php

declare(strict_types=1);

namespace CodeLens\Core\Storage;

use CodeLens\Core\Config\Configuration;

/**
 * Factory for creating storage instances.
 */
final class StorageFactory
{
    /**
     * Create a storage instance based on configuration.
     */
    public static function create(Configuration $config, string $storagePath): StorageInterface
    {
        $driver = $config->getStorageDriver();
        $cacheDir = $config->getCacheDirectory();
        $fullPath = rtrim($storagePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $cacheDir;

        return match ($driver) {
            'sqlite' => new SqliteStorage($fullPath),
            default => new JsonStorage($fullPath),
        };
    }
}

