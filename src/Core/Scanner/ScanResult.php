<?php

declare(strict_types=1);

namespace CodeLens\Core\Scanner;

use CodeLens\Core\Index\FileIndex;
use CodeLens\Core\Index\SymbolRegistry;

/**
 * Result of a scan operation.
 */
final class ScanResult
{
    /**
     * @param array<string, string> $errors Path => error message
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $scannedFiles,
        public readonly int $unchangedFiles,
        public readonly int $removedFiles,
        public readonly int $totalFiles,
        public readonly int $totalSymbols,
        public readonly float $duration,
        public readonly array $errors,
        public readonly FileIndex $fileIndex,
        public readonly SymbolRegistry $symbolRegistry,
    ) {
    }

    /**
     * Check if the scan was successful (no errors).
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if there were errors.
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Get error count.
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Get duration in human-readable format.
     */
    public function getFormattedDuration(): string
    {
        if ($this->duration < 1) {
            return round($this->duration * 1000) . 'ms';
        }

        return round($this->duration, 2) . 's';
    }

    /**
     * Get summary as array.
     */
    public function getSummary(): array
    {
        return [
            'success' => $this->success,
            'scanned_files' => $this->scannedFiles,
            'unchanged_files' => $this->unchangedFiles,
            'removed_files' => $this->removedFiles,
            'total_files' => $this->totalFiles,
            'total_symbols' => $this->totalSymbols,
            'duration' => $this->getFormattedDuration(),
            'error_count' => $this->getErrorCount(),
        ];
    }

    /**
     * Get symbol statistics.
     */
    public function getSymbolStats(): array
    {
        return [
            'classes' => $this->symbolRegistry->countByType('class'),
            'interfaces' => $this->symbolRegistry->countByType('interface'),
            'traits' => $this->symbolRegistry->countByType('trait'),
            'enums' => $this->symbolRegistry->countByType('enum'),
        ];
    }

    /**
     * Get file statistics.
     */
    public function getFileStats(): array
    {
        return $this->fileIndex->getStats();
    }
}
