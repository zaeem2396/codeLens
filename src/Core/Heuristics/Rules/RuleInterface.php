<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics\Rules;

use CodeLens\Core\Heuristics\Flag;
use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Metrics\MetricsResult;

/**
 * Interface for heuristic rules.
 *
 * Each rule examines specific metrics and produces flags
 * when thresholds are exceeded. Rules should use soft,
 * non-judgmental language in their messages.
 */
interface RuleInterface
{
    /**
     * Get unique rule identifier.
     */
    public function getId(): string;

    /**
     * Get human-readable rule name.
     */
    public function getName(): string;

    /**
     * Get description of what this rule checks.
     */
    public function getDescription(): string;

    /**
     * Apply this rule to the metrics and return any flags.
     *
     * @param MetricsResult $metrics The collected metrics
     * @param HeuristicConfig $config Configuration with thresholds
     *
     * @return Flag[] Array of generated flags
     */
    public function apply(MetricsResult $metrics, HeuristicConfig $config): array;

    /**
     * Check if this rule is enabled.
     */
    public function isEnabled(HeuristicConfig $config): bool;
}
