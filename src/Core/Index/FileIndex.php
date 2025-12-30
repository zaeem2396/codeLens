<?php

declare(strict_types=1);

namespace CodeLens\Core\Index;

use CodeLens\Core\Scanner\FileInfo;

/**
 * Index of all files in the codebase.
 * 
 * Tracks file metadata and checksums for incremental scanning.
 */
final class FileIndex
{
    /** @var array<string, FileInfo> Absolute path => FileInfo */
    private array $files = [];

    /**
     * Add or update a file in the index.
     */
    public function add(FileInfo $file): void
    {
        $this->files[$file->absolutePath] = $file;
    }

    /**
     * Get a file by path.
     */
    public function get(string $path): ?FileInfo
    {
        return $this->files[$path] ?? null;
    }

    /**
     * Check if a file exists in the index.
     */
    public function has(string $path): bool
    {
        return isset($this->files[$path]);
    }

    /**
     * Remove a file from the index.
     */
    public function remove(string $path): void
    {
        unset($this->files[$path]);
    }

    /**
     * Get all files.
     * 
     * @return array<string, FileInfo>
     */
    public function all(): array
    {
        return $this->files;
    }

    /**
     * Get file count.
     */
    public function count(): int
    {
        return count($this->files);
    }

    /**
     * Check if a file has changed based on checksum.
     */
    public function hasChanged(FileInfo $file): bool
    {
        $existing = $this->get($file->absolutePath);
        
        if ($existing === null) {
            return true; // New file
        }

        // Compare last modified time first (quick check)
        if ($existing->lastModified !== $file->lastModified) {
            return true;
        }

        // Compare checksums if available
        if ($existing->checksum !== null && $file->checksum !== null) {
            return $existing->checksum !== $file->checksum;
        }

        // Compare size as fallback
        return $existing->size !== $file->size;
    }

    /**
     * Get files that have been removed.
     * 
     * @param array<string, FileInfo> $currentFiles
     * @return array<string> Paths of removed files
     */
    public function getRemovedFiles(array $currentFiles): array
    {
        $removed = [];
        
        foreach (array_keys($this->files) as $path) {
            if (!isset($currentFiles[$path])) {
                $removed[] = $path;
            }
        }

        return $removed;
    }

    /**
     * Clear the index.
     */
    public function clear(): void
    {
        $this->files = [];
    }

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->files as $path => $file) {
            $data[$path] = $file->toArray();
        }
        return $data;
    }

    /**
     * Load from array.
     */
    public static function fromArray(array $data): self
    {
        $index = new self();
        foreach ($data as $path => $fileData) {
            $index->files[$path] = FileInfo::fromArray($fileData);
        }
        return $index;
    }

    /**
     * Get summary statistics.
     */
    public function getStats(): array
    {
        $totalSize = 0;
        $totalLines = 0;

        foreach ($this->files as $file) {
            $totalSize += $file->size;
            if ($file->lineCount !== null) {
                $totalLines += $file->lineCount;
            }
        }

        return [
            'file_count' => $this->count(),
            'total_size' => $totalSize,
            'total_lines' => $totalLines,
        ];
    }
}

