<?php

declare(strict_types=1);

namespace CodeLens\Core\Metrics;

/**
 * Metrics for a single method.
 *
 * Contains raw numerical data without any judgments or labels.
 */
final readonly class MethodMetrics
{
    public function __construct(
        public string $name,
        public string $parentClass,
        public int $lineStart,
        public int $lineEnd,
        public int $lineCount,
        public int $maxNestingDepth,
        public int $conditionalCount,
        public int $loopCount,
        public int $returnCount,
        public int $parameterCount,
        public string $visibility,
        public bool $isStatic,
        public bool $isAbstract,
    ) {
    }

    /**
     * Get method name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get line count.
     */
    public function getLines(): int
    {
        return $this->lineCount;
    }

    /**
     * Get max nesting depth.
     */
    public function getMaxNestingDepth(): int
    {
        return $this->maxNestingDepth;
    }

    /**
     * Get parameter count.
     */
    public function getParameterCount(): int
    {
        return $this->parameterCount;
    }

    /**
     * Get conditional count.
     */
    public function getConditionalCount(): int
    {
        return $this->conditionalCount;
    }

    /**
     * Get return count.
     */
    public function getReturnCount(): int
    {
        return $this->returnCount;
    }

    /**
     * Convert to array for storage/display.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'parent_class' => $this->parentClass,
            'line_start' => $this->lineStart,
            'line_end' => $this->lineEnd,
            'line_count' => $this->lineCount,
            'max_nesting_depth' => $this->maxNestingDepth,
            'conditional_count' => $this->conditionalCount,
            'loop_count' => $this->loopCount,
            'return_count' => $this->returnCount,
            'parameter_count' => $this->parameterCount,
            'visibility' => $this->visibility,
            'is_static' => $this->isStatic,
            'is_abstract' => $this->isAbstract,
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            parentClass: $data['parent_class'],
            lineStart: $data['line_start'],
            lineEnd: $data['line_end'],
            lineCount: $data['line_count'],
            maxNestingDepth: $data['max_nesting_depth'],
            conditionalCount: $data['conditional_count'],
            loopCount: $data['loop_count'],
            returnCount: $data['return_count'],
            parameterCount: $data['parameter_count'],
            visibility: $data['visibility'],
            isStatic: $data['is_static'],
            isAbstract: $data['is_abstract'],
        );
    }
}
