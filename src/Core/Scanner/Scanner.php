<?php

declare(strict_types=1);

namespace CodeLens\Core\Scanner;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Index\FileIndex;
use CodeLens\Core\Index\SymbolRegistry;
use CodeLens\Core\Storage\StorageInterface;

/**
 * Main scanner orchestrator.
 *
 * Coordinates file discovery, parsing, and indexing.
 */
final class Scanner
{
    private Configuration $config;
    private string $basePath;
    private StorageInterface $storage;
    private FileDiscovery $fileDiscovery;
    private AstParser $parser;
    private FileIndex $fileIndex;
    private SymbolRegistry $symbolRegistry;

    /** @var callable|null */
    private $progressCallback = null;

    public function __construct(
        Configuration $config,
        string $basePath,
        StorageInterface $storage,
    ) {
        $this->config = $config;
        $this->basePath = $basePath;
        $this->storage = $storage;
        $this->fileDiscovery = new FileDiscovery($config, $basePath);
        $this->parser = new AstParser();
        $this->fileIndex = new FileIndex();
        $this->symbolRegistry = new SymbolRegistry();
    }

    /**
     * Set progress callback.
     *
     * @param callable(string $file, int $current, int $total): void $callback
     */
    public function onProgress(callable $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    /**
     * Run a full scan.
     */
    public function scan(bool $fresh = false): ScanResult
    {
        $startTime = microtime(true);

        // Load existing data unless fresh scan
        if (! $fresh && $this->storage->hasData()) {
            $this->fileIndex = $this->storage->loadFileIndex();
            $this->symbolRegistry = $this->storage->loadSymbolRegistry();
        } else {
            $this->fileIndex = new FileIndex();
            $this->symbolRegistry = new SymbolRegistry();
        }

        // Discover files
        $discoveredFiles = $this->fileDiscovery->discover();

        // Determine what needs to be scanned
        $filesToScan = [];
        $unchangedFiles = [];

        foreach ($discoveredFiles as $path => $fileInfo) {
            $fileWithChecksum = $fileInfo->withChecksum();

            if (! $fresh && ! $this->fileIndex->hasChanged($fileWithChecksum)) {
                $unchangedFiles[] = $path;
            } else {
                $filesToScan[$path] = $fileWithChecksum;
            }
        }

        // Handle removed files
        $removedFiles = $this->fileIndex->getRemovedFiles($discoveredFiles);
        foreach ($removedFiles as $path) {
            $this->fileIndex->remove($path);
            $this->symbolRegistry->removeByFile($path);
        }

        // Scan files
        $totalFiles = count($filesToScan);
        $current = 0;
        $errors = [];
        $scannedCount = 0;

        foreach ($filesToScan as $path => $fileInfo) {
            $current++;

            if ($this->progressCallback !== null) {
                ($this->progressCallback)($fileInfo->relativePath, $current, $totalFiles);
            }

            // Remove old symbols from this file
            $this->symbolRegistry->removeByFile($path);

            // Parse file
            $parseResult = $this->parser->parseFile($path);

            if ($parseResult->isError()) {
                $errors[$path] = $parseResult->error;
                continue;
            }

            // Update file index with line count
            $fileWithLines = $fileInfo->withLineCount();
            $this->fileIndex->add($fileWithLines);

            // Register symbols
            foreach ($parseResult->symbols as $symbol) {
                $this->symbolRegistry->register($symbol);
            }

            $scannedCount++;
        }

        // Save results
        $this->storage->saveFileIndex($this->fileIndex);
        $this->storage->saveSymbolRegistry($this->symbolRegistry);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Save metadata
        $metadata = [
            'last_scan' => date('c'),
            'duration_seconds' => round($duration, 3),
            'scanned_files' => $scannedCount,
            'unchanged_files' => count($unchangedFiles),
            'removed_files' => count($removedFiles),
            'total_files' => $this->fileIndex->count(),
            'total_symbols' => $this->symbolRegistry->count(),
            'errors' => count($errors),
        ];
        $this->storage->saveScanMetadata($metadata);

        return new ScanResult(
            success: count($errors) === 0,
            scannedFiles: $scannedCount,
            unchangedFiles: count($unchangedFiles),
            removedFiles: count($removedFiles),
            totalFiles: $this->fileIndex->count(),
            totalSymbols: $this->symbolRegistry->count(),
            duration: $duration,
            errors: $errors,
            fileIndex: $this->fileIndex,
            symbolRegistry: $this->symbolRegistry,
        );
    }

    /**
     * Scan a specific path.
     */
    public function scanPath(string $path): ScanResult
    {
        $startTime = microtime(true);

        // Load existing data
        if ($this->storage->hasData()) {
            $this->fileIndex = $this->storage->loadFileIndex();
            $this->symbolRegistry = $this->storage->loadSymbolRegistry();
        }

        // Discover files in path
        $discoveredFiles = $this->fileDiscovery->discoverInPath($path);

        $totalFiles = count($discoveredFiles);
        $current = 0;
        $errors = [];
        $scannedCount = 0;

        foreach ($discoveredFiles as $filePath => $fileInfo) {
            $current++;

            if ($this->progressCallback !== null) {
                ($this->progressCallback)($fileInfo->relativePath, $current, $totalFiles);
            }

            // Remove old symbols from this file
            $this->symbolRegistry->removeByFile($filePath);

            // Parse file
            $fileWithChecksum = $fileInfo->withChecksum()->withLineCount();
            $parseResult = $this->parser->parseFile($filePath);

            if ($parseResult->isError()) {
                $errors[$filePath] = $parseResult->error;
                continue;
            }

            // Update file index
            $this->fileIndex->add($fileWithChecksum);

            // Register symbols
            foreach ($parseResult->symbols as $symbol) {
                $this->symbolRegistry->register($symbol);
            }

            $scannedCount++;
        }

        // Save results
        $this->storage->saveFileIndex($this->fileIndex);
        $this->storage->saveSymbolRegistry($this->symbolRegistry);

        $endTime = microtime(true);

        return new ScanResult(
            success: count($errors) === 0,
            scannedFiles: $scannedCount,
            unchangedFiles: 0,
            removedFiles: 0,
            totalFiles: $this->fileIndex->count(),
            totalSymbols: $this->symbolRegistry->count(),
            duration: $endTime - $startTime,
            errors: $errors,
            fileIndex: $this->fileIndex,
            symbolRegistry: $this->symbolRegistry,
        );
    }

    /**
     * Get the file index.
     */
    public function getFileIndex(): FileIndex
    {
        return $this->fileIndex;
    }

    /**
     * Get the symbol registry.
     */
    public function getSymbolRegistry(): SymbolRegistry
    {
        return $this->symbolRegistry;
    }

    /**
     * Get the configuration.
     */
    public function getConfiguration(): Configuration
    {
        return $this->config;
    }

    /**
     * Get the base path.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
