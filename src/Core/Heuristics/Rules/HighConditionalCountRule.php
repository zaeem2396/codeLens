<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics\Rules;

use CodeLens\Core\Heuristics\Flag;
use CodeLens\Core\Heuristics\FlagLevel;
use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Metrics\MetricsResult;

/**
 * Detects methods with many conditional statements.
 */
final class HighConditionalCountRule implements RuleInterface
{
    public function getId(): string
    {
        return 'high_conditionals';
    }

    public function getName(): string
    {
        return 'High Conditional Count';
    }

    public function getDescription(): string
    {
        return 'Identifies methods with many conditional statements that might indicate complex decision logic.';
    }

    public function apply(MetricsResult $metrics, HeuristicConfig $config): array
    {
        if (! $this->isEnabled($config)) {
            return [];
        }

        $flags = [];
        $mildThreshold = $config->getThreshold('conditionals_mild', 10);
        $attentionThreshold = $config->getThreshold('conditionals_attention', 20);

        foreach ($metrics->getFileMetrics() as $fileMetrics) {
            $fileName = basename($fileMetrics->getPath());

            foreach ($fileMetrics->getMethodMetrics() as $methodMetrics) {
                $count = $methodMetrics->getConditionalCount();

                if ($count >= $attentionThreshold) {
                    $flags[] = new Flag(
                        ruleId: $this->getId(),
                        ruleName: $this->getName(),
                        level: FlagLevel::Attention,
                        target: $fileName . '::' . $methodMetrics->getName(),
                        targetType: 'method',
                        value: $count,
                        threshold: $attentionThreshold,
                        message: sprintf(
                            'Method %s::%s has %d conditionals (threshold: %d)',
                            $fileName,
                            $methodMetrics->getName(),
                            $count,
                            $attentionThreshold,
                        ),
                        reasoning: 'Many conditionals often indicate complex decision logic. ' .
                            'Consider if this could be simplified with polymorphism or strategy patterns.',
                    );
                } elseif ($count >= $mildThreshold) {
                    $flags[] = new Flag(
                        ruleId: $this->getId(),
                        ruleName: $this->getName(),
                        level: FlagLevel::Mild,
                        target: $fileName . '::' . $methodMetrics->getName(),
                        targetType: 'method',
                        value: $count,
                        threshold: $mildThreshold,
                        message: sprintf(
                            'Method %s::%s has %d conditionals (threshold: %d)',
                            $fileName,
                            $methodMetrics->getName(),
                            $count,
                            $mildThreshold,
                        ),
                        reasoning: 'A moderate number of conditionals. Some business logic ' .
                            'genuinely requires many branches.',
                    );
                }
            }
        }

        return $flags;
    }

    public function isEnabled(HeuristicConfig $config): bool
    {
        return $config->isRuleEnabled($this->getId());
    }
}
