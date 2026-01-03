<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics;

use CodeLens\Core\Heuristics\Rules\DeepNestingRule;
use CodeLens\Core\Heuristics\Rules\HighConditionalCountRule;
use CodeLens\Core\Heuristics\Rules\LargeFileRule;
use CodeLens\Core\Heuristics\Rules\LongMethodRule;
use CodeLens\Core\Heuristics\Rules\ManyParametersRule;
use CodeLens\Core\Heuristics\Rules\ManyReturnsRule;
use CodeLens\Core\Heuristics\Rules\MultipleClassesPerFileRule;
use CodeLens\Core\Heuristics\Rules\RuleInterface;
use CodeLens\Core\Metrics\MetricsResult;

/**
 * Engine that applies heuristic rules to metrics.
 *
 * The engine orchestrates the application of all enabled rules
 * and aggregates their flags into a single result.
 */
final class HeuristicEngine
{
    /** @var RuleInterface[] */
    private array $rules = [];

    private HeuristicConfig $config;

    public function __construct(?HeuristicConfig $config = null)
    {
        $this->config = $config ?? new HeuristicConfig();
        $this->registerDefaultRules();
    }

    /**
     * Register the default set of rules.
     */
    private function registerDefaultRules(): void
    {
        $this->registerRule(new LongMethodRule());
        $this->registerRule(new DeepNestingRule());
        $this->registerRule(new ManyParametersRule());
        $this->registerRule(new HighConditionalCountRule());
        $this->registerRule(new LargeFileRule());
        $this->registerRule(new MultipleClassesPerFileRule());
        $this->registerRule(new ManyReturnsRule());
    }

    /**
     * Register a custom rule.
     */
    public function registerRule(RuleInterface $rule): void
    {
        $this->rules[$rule->getId()] = $rule;
    }

    /**
     * Remove a rule by ID.
     */
    public function removeRule(string $ruleId): void
    {
        unset($this->rules[$ruleId]);
    }

    /**
     * Get all registered rules.
     *
     * @return RuleInterface[]
     */
    public function getRules(): array
    {
        return array_values($this->rules);
    }

    /**
     * Get the configuration.
     */
    public function getConfig(): HeuristicConfig
    {
        return $this->config;
    }

    /**
     * Apply all enabled rules to the metrics.
     */
    public function analyze(MetricsResult $metrics): HeuristicResult
    {
        $result = new HeuristicResult();

        foreach ($this->rules as $rule) {
            if ($rule->isEnabled($this->config)) {
                $flags = $rule->apply($metrics, $this->config);
                $result->addFlags($flags);
            }
        }

        return $result;
    }

    /**
     * Apply a specific rule to the metrics.
     */
    public function applyRule(string $ruleId, MetricsResult $metrics): HeuristicResult
    {
        $result = new HeuristicResult();

        if (isset($this->rules[$ruleId])) {
            $flags = $this->rules[$ruleId]->apply($metrics, $this->config);
            $result->addFlags($flags);
        }

        return $result;
    }

    /**
     * Get a summary of available rules.
     */
    public function getRulesSummary(): array
    {
        $summary = [];

        foreach ($this->rules as $rule) {
            $summary[] = [
                'id' => $rule->getId(),
                'name' => $rule->getName(),
                'description' => $rule->getDescription(),
                'enabled' => $rule->isEnabled($this->config),
            ];
        }

        return $summary;
    }
}
