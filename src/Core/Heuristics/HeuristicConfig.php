<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics;

/**
 * Configuration for heuristic rules and thresholds.
 *
 * All thresholds are configurable. Default values are
 * intentionally conservative to avoid noise.
 */
final class HeuristicConfig
{
    /**
     * @param array<string, bool> $enabledRules Rule ID => enabled state
     * @param array<string, mixed> $thresholds Threshold name => value
     */
    public function __construct(
        private array $enabledRules = [],
        private array $thresholds = [],
    ) {
        // Set sensible defaults for all thresholds
        $this->thresholds = array_merge($this->getDefaults(), $thresholds);
    }

    /**
     * Get default thresholds.
     */
    public function getDefaults(): array
    {
        return [
            // Method length thresholds
            'method_lines_mild' => 50,
            'method_lines_attention' => 100,

            // Nesting depth thresholds
            'nesting_depth_mild' => 4,
            'nesting_depth_attention' => 6,

            // Parameter count thresholds
            'parameter_count_mild' => 5,
            'parameter_count_attention' => 8,

            // Conditional count thresholds
            'conditionals_mild' => 10,
            'conditionals_attention' => 20,

            // File size thresholds (lines)
            'file_lines_mild' => 300,
            'file_lines_attention' => 500,

            // Class count per file
            'classes_per_file_mild' => 2,
            'classes_per_file_attention' => 3,

            // Method count per class
            'methods_per_class_mild' => 15,
            'methods_per_class_attention' => 25,

            // Return statement count
            'return_count_mild' => 5,
            'return_count_attention' => 10,
        ];
    }

    /**
     * Get a threshold value.
     */
    public function getThreshold(string $name, mixed $default = null): mixed
    {
        return $this->thresholds[$name] ?? $default;
    }

    /**
     * Set a threshold value.
     */
    public function setThreshold(string $name, mixed $value): self
    {
        $this->thresholds[$name] = $value;

        return $this;
    }

    /**
     * Check if a rule is enabled.
     */
    public function isRuleEnabled(string $ruleId): bool
    {
        // Default to enabled if not explicitly disabled
        return $this->enabledRules[$ruleId] ?? true;
    }

    /**
     * Enable a rule.
     */
    public function enableRule(string $ruleId): self
    {
        $this->enabledRules[$ruleId] = true;

        return $this;
    }

    /**
     * Disable a rule.
     */
    public function disableRule(string $ruleId): self
    {
        $this->enabledRules[$ruleId] = false;

        return $this;
    }

    /**
     * Get all thresholds.
     */
    public function getAllThresholds(): array
    {
        return $this->thresholds;
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabledRules: $data['enabled_rules'] ?? [],
            thresholds: $data['thresholds'] ?? [],
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'enabled_rules' => $this->enabledRules,
            'thresholds' => $this->thresholds,
        ];
    }
}
