<?php

declare(strict_types=1);

namespace CodeLens\Core\Storage;

use CodeLens\Core\Index\FileIndex;
use CodeLens\Core\Index\SymbolRegistry;
use CodeLens\Core\Index\Symbols\ClassSymbol;
use CodeLens\Core\Index\Symbols\EnumSymbol;
use CodeLens\Core\Index\Symbols\InterfaceSymbol;
use CodeLens\Core\Index\Symbols\TraitSymbol;

/**
 * JSON file-based storage implementation.
 */
final class JsonStorage implements StorageInterface
{
    private string $storagePath;

    public function __construct(string $storagePath)
    {
        $this->storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);
    }

    /**
     * Save the file index.
     */
    public function saveFileIndex(FileIndex $index): void
    {
        $this->ensureDirectory();
        $this->writeJson($this->getFileIndexPath(), $index->toArray());
    }

    /**
     * Load the file index.
     */
    public function loadFileIndex(): FileIndex
    {
        $data = $this->readJson($this->getFileIndexPath());
        
        if ($data === null) {
            return new FileIndex();
        }

        return FileIndex::fromArray($data);
    }

    /**
     * Save the symbol registry.
     */
    public function saveSymbolRegistry(SymbolRegistry $registry): void
    {
        $this->ensureDirectory();
        $this->writeJson($this->getSymbolRegistryPath(), $registry->toArray());
    }

    /**
     * Load the symbol registry.
     */
    public function loadSymbolRegistry(): SymbolRegistry
    {
        $data = $this->readJson($this->getSymbolRegistryPath());
        
        if ($data === null) {
            return new SymbolRegistry();
        }

        $registry = new SymbolRegistry();
        
        foreach ($data as $symbolData) {
            $symbol = $this->createSymbolFromArray($symbolData);
            if ($symbol !== null) {
                $registry->register($symbol);
            }
        }

        return $registry;
    }

    /**
     * Save scan metadata.
     */
    public function saveScanMetadata(array $metadata): void
    {
        $this->ensureDirectory();
        $this->writeJson($this->getMetadataPath(), $metadata);
    }

    /**
     * Load scan metadata.
     */
    public function loadScanMetadata(): array
    {
        return $this->readJson($this->getMetadataPath()) ?? [];
    }

    /**
     * Check if storage has data.
     */
    public function hasData(): bool
    {
        return file_exists($this->getFileIndexPath()) || 
               file_exists($this->getSymbolRegistryPath());
    }

    /**
     * Clear all stored data.
     */
    public function clear(): void
    {
        $files = [
            $this->getFileIndexPath(),
            $this->getSymbolRegistryPath(),
            $this->getMetadataPath(),
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Get storage path.
     */
    public function getPath(): string
    {
        return $this->storagePath;
    }

    /**
     * Get file index path.
     */
    private function getFileIndexPath(): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . 'file_index.json';
    }

    /**
     * Get symbol registry path.
     */
    private function getSymbolRegistryPath(): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . 'symbols.json';
    }

    /**
     * Get metadata path.
     */
    private function getMetadataPath(): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . 'metadata.json';
    }

    /**
     * Ensure storage directory exists.
     */
    private function ensureDirectory(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Write data to JSON file.
     */
    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
        }

        $result = file_put_contents($path, $json, LOCK_EX);
        
        if ($result === false) {
            throw new \RuntimeException("Failed to write to file: {$path}");
        }
    }

    /**
     * Read data from JSON file.
     */
    private function readJson(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Create a symbol from array data.
     */
    private function createSymbolFromArray(array $data): ?object
    {
        $type = $data['type'] ?? null;

        return match ($type) {
            'class' => ClassSymbol::fromArray($data),
            'interface' => InterfaceSymbol::fromArray($data),
            'trait' => TraitSymbol::fromArray($data),
            'enum' => EnumSymbol::fromArray($data),
            default => null,
        };
    }
}

