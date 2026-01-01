<?php

declare(strict_types=1);

namespace CodeLens\Core\Metrics;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Scanner\FileDiscovery;

/**
 * Analyzes metrics for the entire codebase.
 *
 * Orchestrates file discovery and metrics calculation.
 */
final class MetricsAnalyzer
{
    private Configuration $config;
    private string $basePath;
    private FileDiscovery $fileDiscovery;
    private MetricsCalculator $calculator;

    /** @var callable|null */
    private $progressCallback = null;

    public function __construct(Configuration $config, string $basePath)
    {
        $this->config = $config;
        $this->basePath = $basePath;
        $this->fileDiscovery = new FileDiscovery($config, $basePath);
        $this->calculator = new MetricsCalculator();
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
     * Analyze all files in configured paths.
     */
    public function analyze(): MetricsResult
    {
        $startTime = microtime(true);

        $files = $this->fileDiscovery->discover();
        $totalFiles = count($files);
        $current = 0;
        $fileMetrics = [];

        foreach ($files as $path => $fileInfo) {
            $current++;

            if ($this->progressCallback !== null) {
                ($this->progressCallback)($fileInfo->relativePath, $current, $totalFiles);
            }

            $metrics = $this->calculator->calculateForFile($path, $fileInfo->relativePath);

            if ($metrics !== null) {
                $fileMetrics[] = $metrics;
            }
        }

        $duration = microtime(true) - $startTime;

        return new MetricsResult($fileMetrics, $duration);
    }

    /**
     * Analyze a specific path.
     */
    public function analyzePath(string $path): MetricsResult
    {
        $startTime = microtime(true);

        $files = $this->fileDiscovery->discoverInPath($path);
        $totalFiles = count($files);
        $current = 0;
        $fileMetrics = [];

        foreach ($files as $filePath => $fileInfo) {
            $current++;

            if ($this->progressCallback !== null) {
                ($this->progressCallback)($fileInfo->relativePath, $current, $totalFiles);
            }

            $metrics = $this->calculator->calculateForFile($filePath, $fileInfo->relativePath);

            if ($metrics !== null) {
                $fileMetrics[] = $metrics;
            }
        }

        $duration = microtime(true) - $startTime;

        return new MetricsResult($fileMetrics, $duration);
    }

    /**
     * Analyze a single file.
     */
    public function analyzeFile(string $filePath): ?FileMetrics
    {
        $relativePath = $filePath;
        if (str_starts_with($filePath, $this->basePath)) {
            $relativePath = ltrim(substr($filePath, strlen($this->basePath)), DIRECTORY_SEPARATOR);
        }

        return $this->calculator->calculateForFile($filePath, $relativePath);
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
