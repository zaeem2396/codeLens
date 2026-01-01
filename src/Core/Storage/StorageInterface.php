<?php

declare(strict_types=1);

namespace CodeLens\Core\Storage;

use CodeLens\Core\Index\FileIndex;
use CodeLens\Core\Index\SymbolRegistry;

/**
 * Interface for storage backends.
 */
interface StorageInterface
{
    /**
     * Save the file index.
     */
    public function saveFileIndex(FileIndex $index): void;

    /**
     * Load the file index.
     */
    public function loadFileIndex(): FileIndex;

    /**
     * Save the symbol registry.
     */
    public function saveSymbolRegistry(SymbolRegistry $registry): void;

    /**
     * Load the symbol registry.
     */
    public function loadSymbolRegistry(): SymbolRegistry;

    /**
     * Save scan metadata.
     */
    public function saveScanMetadata(array $metadata): void;

    /**
     * Load scan metadata.
     */
    public function loadScanMetadata(): array;

    /**
     * Check if storage has data.
     */
    public function hasData(): bool;

    /**
     * Clear all stored data.
     */
    public function clear(): void;

    /**
     * Get storage path.
     */
    public function getPath(): string;
}
