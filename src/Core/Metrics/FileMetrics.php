<?php

declare(strict_types=1);

namespace CodeLens\Core\Metrics;

/**
 * Metrics for a single file.
 *
 * Contains raw numerical data without any judgments or labels.
 */
final class FileMetrics
{
    /**
     * @param array<MethodMetrics> $methodMetrics
     */
    public function __construct(
        public readonly string $filePath,
        public readonly string $relativePath,
        public readonly int $linesOfCode,
        public readonly int $linesOfCodeWithoutComments,
        public readonly int $blankLines,
        public readonly int $commentLines,
        public readonly int $classCount,
        public readonly int $interfaceCount,
        public readonly int $traitCount,
        public readonly int $enumCount,
        public readonly int $methodCount,
        public readonly int $propertyCount,
        public readonly array $methodMetrics = [],
    ) {
    }

    /**
     * Get file path.
     */
    public function getPath(): string
    {
        return $this->filePath;
    }

    /**
     * Get lines of code.
     */
    public function getLines(): int
    {
        return $this->linesOfCode;
    }

    /**
     * Get class count.
     */
    public function getClassCount(): int
    {
        return $this->classCount;
    }

    /**
     * Get method metrics.
     *
     * @return array<MethodMetrics>
     */
    public function getMethodMetrics(): array
    {
        return $this->methodMetrics;
    }

    /**
     * Get total symbol count.
     */
    public function getTotalSymbolCount(): int
    {
        return $this->classCount + $this->interfaceCount + $this->traitCount + $this->enumCount;
    }

    /**
     * Get average method length.
     */
    public function getAverageMethodLength(): float
    {
        if ($this->methodCount === 0) {
            return 0.0;
        }

        $totalLines = 0;
        foreach ($this->methodMetrics as $method) {
            $totalLines += $method->lineCount;
        }

        return round($totalLines / $this->methodCount, 2);
    }

    /**
     * Get max nesting depth across all methods.
     */
    public function getMaxNestingDepth(): int
    {
        $maxDepth = 0;
        foreach ($this->methodMetrics as $method) {
            if ($method->maxNestingDepth > $maxDepth) {
                $maxDepth = $method->maxNestingDepth;
            }
        }

        return $maxDepth;
    }

    /**
     * Convert to array for storage/display.
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'relative_path' => $this->relativePath,
            'lines_of_code' => $this->linesOfCode,
            'lines_of_code_without_comments' => $this->linesOfCodeWithoutComments,
            'blank_lines' => $this->blankLines,
            'comment_lines' => $this->commentLines,
            'class_count' => $this->classCount,
            'interface_count' => $this->interfaceCount,
            'trait_count' => $this->traitCount,
            'enum_count' => $this->enumCount,
            'method_count' => $this->methodCount,
            'property_count' => $this->propertyCount,
            'average_method_length' => $this->getAverageMethodLength(),
            'max_nesting_depth' => $this->getMaxNestingDepth(),
            'method_metrics' => array_map(fn ($m) => $m->toArray(), $this->methodMetrics),
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            filePath: $data['file_path'],
            relativePath: $data['relative_path'],
            linesOfCode: $data['lines_of_code'],
            linesOfCodeWithoutComments: $data['lines_of_code_without_comments'],
            blankLines: $data['blank_lines'],
            commentLines: $data['comment_lines'],
            classCount: $data['class_count'],
            interfaceCount: $data['interface_count'],
            traitCount: $data['trait_count'],
            enumCount: $data['enum_count'],
            methodCount: $data['method_count'],
            propertyCount: $data['property_count'],
            methodMetrics: array_map(
                fn ($m) => MethodMetrics::fromArray($m),
                $data['method_metrics'] ?? [],
            ),
        );
    }
}
