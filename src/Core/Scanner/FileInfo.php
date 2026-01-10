<?php

declare(strict_types=1);

namespace CodeLens\Core\Scanner;

/**
 * Represents information about a discovered file.
 */
final class FileInfo
{
    public function __construct(
        public readonly string $absolutePath,
        public readonly string $relativePath,
        public readonly int $size,
        public readonly int $lastModified,
        public readonly ?string $checksum = null,
        public readonly ?int $lineCount = null,
    ) {
    }

    /**
     * Calculate the checksum of the file.
     */
    public function withChecksum(): self
    {
        if ($this->checksum !== null) {
            return $this;
        }

        $hash = @hash_file('sha256', $this->absolutePath);

        return new self(
            absolutePath: $this->absolutePath,
            relativePath: $this->relativePath,
            size: $this->size,
            lastModified: $this->lastModified,
            checksum: $hash ?: null,
            lineCount: $this->lineCount,
        );
    }

    /**
     * Count lines in the file.
     */
    public function withLineCount(): self
    {
        if ($this->lineCount !== null) {
            return $this;
        }

        $lineCount = 0;
        $handle = @fopen($this->absolutePath, 'r');

        if ($handle !== false) {
            while (! feof($handle)) {
                fgets($handle);
                $lineCount++;
            }
            fclose($handle);
        }

        return new self(
            absolutePath: $this->absolutePath,
            relativePath: $this->relativePath,
            size: $this->size,
            lastModified: $this->lastModified,
            checksum: $this->checksum,
            lineCount: $lineCount,
        );
    }

    /**
     * Get the file extension.
     */
    public function getExtension(): string
    {
        return pathinfo($this->absolutePath, PATHINFO_EXTENSION);
    }

    /**
     * Get the filename without extension.
     */
    public function getBasename(): string
    {
        return pathinfo($this->absolutePath, PATHINFO_FILENAME);
    }

    /**
     * Get the directory path.
     */
    public function getDirectory(): string
    {
        return dirname($this->absolutePath);
    }

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        return [
            'absolute_path' => $this->absolutePath,
            'relative_path' => $this->relativePath,
            'size' => $this->size,
            'last_modified' => $this->lastModified,
            'checksum' => $this->checksum,
            'line_count' => $this->lineCount,
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            absolutePath: $data['absolute_path'],
            relativePath: $data['relative_path'],
            size: $data['size'],
            lastModified: $data['last_modified'],
            checksum: $data['checksum'] ?? null,
            lineCount: $data['line_count'] ?? null,
        );
    }
}
