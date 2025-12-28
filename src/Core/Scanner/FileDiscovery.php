<?php

declare(strict_types=1);

namespace CodeLens\Core\Scanner;

use CodeLens\Core\Config\Configuration;
use Symfony\Component\Finder\Finder;

/**
 * Discovers PHP files in configured paths.
 * 
 * Respects include/exclude paths and file extensions
 * from the configuration.
 */
final class FileDiscovery
{
    private Configuration $config;
    private string $basePath;

    public function __construct(Configuration $config, string $basePath)
    {
        $this->config = $config;
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    /**
     * Discover all PHP files based on configuration.
     * 
     * @return array<string, FileInfo> Indexed by absolute path
     */
    public function discover(): array
    {
        $files = [];
        $scanPaths = $this->config->getScanPaths();
        $excludePaths = $this->config->getExcludePaths();
        $extensions = $this->config->getFileExtensions();

        foreach ($scanPaths as $scanPath) {
            $absolutePath = $this->resolvePath($scanPath);
            
            if (!is_dir($absolutePath)) {
                continue;
            }

            $finder = $this->createFinder($absolutePath, $excludePaths, $extensions);

            foreach ($finder as $file) {
                $path = $file->getRealPath();
                if ($path === false) {
                    continue;
                }

                $files[$path] = new FileInfo(
                    absolutePath: $path,
                    relativePath: $this->getRelativePath($path),
                    size: $file->getSize(),
                    lastModified: $file->getMTime()
                );
            }
        }

        return $files;
    }

    /**
     * Discover files in a specific path.
     * 
     * @return array<string, FileInfo>
     */
    public function discoverInPath(string $path): array
    {
        $absolutePath = $this->resolvePath($path);
        
        if (!is_dir($absolutePath) && !is_file($absolutePath)) {
            return [];
        }

        if (is_file($absolutePath)) {
            return [
                $absolutePath => new FileInfo(
                    absolutePath: $absolutePath,
                    relativePath: $this->getRelativePath($absolutePath),
                    size: filesize($absolutePath) ?: 0,
                    lastModified: filemtime($absolutePath) ?: 0
                )
            ];
        }

        $files = [];
        $excludePaths = $this->config->getExcludePaths();
        $extensions = $this->config->getFileExtensions();
        
        $finder = $this->createFinder($absolutePath, $excludePaths, $extensions);

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $files[$filePath] = new FileInfo(
                absolutePath: $filePath,
                relativePath: $this->getRelativePath($filePath),
                size: $file->getSize(),
                lastModified: $file->getMTime()
            );
        }

        return $files;
    }

    /**
     * Create a configured Finder instance.
     */
    private function createFinder(string $path, array $excludePaths, array $extensions): Finder
    {
        $finder = new Finder();
        $finder->files()->in($path);

        // Add file extension filters
        foreach ($extensions as $ext) {
            $finder->name('*.' . ltrim($ext, '.'));
        }

        // Exclude paths
        foreach ($excludePaths as $excludePath) {
            $finder->notPath($excludePath);
        }

        // Ignore unreadable directories
        $finder->ignoreUnreadableDirs();

        return $finder;
    }

    /**
     * Resolve a path relative to base path.
     */
    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return $this->basePath . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Get path relative to base path.
     */
    private function getRelativePath(string $absolutePath): string
    {
        if (str_starts_with($absolutePath, $this->basePath)) {
            return ltrim(substr($absolutePath, strlen($this->basePath)), DIRECTORY_SEPARATOR);
        }

        return $absolutePath;
    }
}

