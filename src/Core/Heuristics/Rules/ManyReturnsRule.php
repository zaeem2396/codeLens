<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics\Rules;

use CodeLens\Core\Heuristics\Flag;
use CodeLens\Core\Heuristics\FlagLevel;
use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Metrics\MetricsResult;

/**
 * Detects methods with many return statements.
 */
final class ManyReturnsRule implements RuleInterface
{
    public function getId(): string
    {
        return 'many_returns';
    }

    public function getName(): string
    {
        return 'Many Return Statements';
    }

    public function getDescription(): string
    {
        return 'Identifies methods with many return statements that might have complex exit paths.';
    }

    public function apply(MetricsResult $metrics, HeuristicConfig $config): array
    {
        if (! $this->isEnabled($config)) {
            return [];
        }

        $flags = [];
        $mildThreshold = $config->getThreshold('return_count_mild', 5);
        $attentionThreshold = $config->getThreshold('return_count_attention', 10);

        foreach ($metrics->getFileMetrics() as $fileMetrics) {
            $fileName = basename($fileMetrics->getPath());

            foreach ($fileMetrics->getMethodMetrics() as $methodMetrics) {
                $count = $methodMetrics->getReturnCount();

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
                            'Method %s::%s has %d return statements (threshold: %d)',
                            $fileName,
                            $methodMetrics->getName(),
                            $count,
                            $attentionThreshold,
                        ),
                        reasoning: 'Many return points can make control flow harder to follow. ' .
                            'Consider if the method could be structured more linearly.',
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
                            'Method %s::%s has %d return statements (threshold: %d)',
                            $fileName,
                            $methodMetrics->getName(),
                            $count,
                            $mildThreshold,
                        ),
                        reasoning: 'A moderate number of return points. Early returns are often a good pattern for guard clauses.',
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
