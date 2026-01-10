<?php

declare(strict_types=1);

namespace CodeLens\Core\Metrics;

/**
 * Aggregated metrics result for multiple files.
 *
 * Contains raw numerical summaries without judgments.
 */
final class MetricsResult
{
    /**
     * @param array<FileMetrics> $fileMetrics
     */
    public function __construct(
        public readonly array $fileMetrics,
        public readonly float $duration,
    ) {
    }

    /**
     * Get all file metrics.
     *
     * @return array<FileMetrics>
     */
    public function getFileMetrics(): array
    {
        return $this->fileMetrics;
    }

    /**
     * Get file count.
     */
    public function getFileCount(): int
    {
        return count($this->fileMetrics);
    }

    /**
     * Get total lines of code.
     */
    public function getTotalLinesOfCode(): int
    {
        $total = 0;
        foreach ($this->fileMetrics as $file) {
            $total += $file->linesOfCode;
        }

        return $total;
    }

    /**
     * Get total lines of code without comments.
     */
    public function getTotalLinesOfCodeWithoutComments(): int
    {
        $total = 0;
        foreach ($this->fileMetrics as $file) {
            $total += $file->linesOfCodeWithoutComments;
        }

        return $total;
    }

    /**
     * Get total class count.
     */
    public function getTotalClassCount(): int
    {
        $total = 0;
        foreach ($this->fileMetrics as $file) {
            $total += $file->classCount;
        }

        return $total;
    }

    /**
     * Get total interface count.
     */
    public function getTotalInterfaceCount(): int
    {
        $total = 0;
        foreach ($this->fileMetrics as $file) {
            $total += $file->interfaceCount;
        }

        return $total;
    }

    /**
     * Get total trait count.
     */
    public function getTotalTraitCount(): int
    {
        $total = 0;
        foreach ($this->fileMetrics as $file) {
            $total += $file->traitCount;
        }

        return $total;
    }

    /**
     * Get total enum count.
     */
    public function getTotalEnumCount(): int
    {
        $total = 0;
        foreach ($this->fileMetrics as $file) {
            $total += $file->enumCount;
        }

        return $total;
    }

    /**
     * Get total method count.
     */
    public function getTotalMethodCount(): int
    {
        $total = 0;
        foreach ($this->fileMetrics as $file) {
            $total += $file->methodCount;
        }

        return $total;
    }

    /**
     * Get average method length.
     */
    public function getAverageMethodLength(): float
    {
        $totalMethods = $this->getTotalMethodCount();
        if ($totalMethods === 0) {
            return 0.0;
        }

        $totalLines = 0;
        foreach ($this->fileMetrics as $file) {
            foreach ($file->methodMetrics as $method) {
                $totalLines += $method->lineCount;
            }
        }

        return round($totalLines / $totalMethods, 2);
    }

    /**
     * Get max nesting depth across all files.
     */
    public function getMaxNestingDepth(): int
    {
        $maxDepth = 0;
        foreach ($this->fileMetrics as $file) {
            $fileMax = $file->getMaxNestingDepth();
            if ($fileMax > $maxDepth) {
                $maxDepth = $fileMax;
            }
        }

        return $maxDepth;
    }

    /**
     * Get formatted duration.
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
            'file_count' => $this->getFileCount(),
            'total_lines_of_code' => $this->getTotalLinesOfCode(),
            'total_lines_without_comments' => $this->getTotalLinesOfCodeWithoutComments(),
            'total_classes' => $this->getTotalClassCount(),
            'total_interfaces' => $this->getTotalInterfaceCount(),
            'total_traits' => $this->getTotalTraitCount(),
            'total_enums' => $this->getTotalEnumCount(),
            'total_methods' => $this->getTotalMethodCount(),
            'average_method_length' => $this->getAverageMethodLength(),
            'max_nesting_depth' => $this->getMaxNestingDepth(),
            'duration' => $this->getFormattedDuration(),
        ];
    }

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->getSummary(),
            'files' => array_map(fn ($f) => $f->toArray(), $this->fileMetrics),
        ];
    }
}
