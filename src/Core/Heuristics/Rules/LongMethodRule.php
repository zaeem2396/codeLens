<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics\Rules;

use CodeLens\Core\Heuristics\Flag;
use CodeLens\Core\Heuristics\FlagLevel;
use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Metrics\MetricsResult;

/**
 * Detects methods that may benefit from being broken down.
 */
final class LongMethodRule implements RuleInterface
{
    public function getId(): string
    {
        return 'long_method';
    }

    public function getName(): string
    {
        return 'Long Method';
    }

    public function getDescription(): string
    {
        return 'Identifies methods with many lines of code that might be candidates for extraction.';
    }

    public function apply(MetricsResult $metrics, HeuristicConfig $config): array
    {
        if (! $this->isEnabled($config)) {
            return [];
        }

        $flags = [];
        $mildThreshold = $config->getThreshold('method_lines_mild', 50);
        $attentionThreshold = $config->getThreshold('method_lines_attention', 100);

        foreach ($metrics->getFileMetrics() as $fileMetrics) {
            $fileName = basename($fileMetrics->getPath());

            foreach ($fileMetrics->getMethodMetrics() as $methodMetrics) {
                $lines = $methodMetrics->getLines();

                if ($lines >= $attentionThreshold) {
                    $flags[] = new Flag(
                        ruleId: $this->getId(),
                        ruleName: $this->getName(),
                        level: FlagLevel::Attention,
                        target: $fileName . '::' . $methodMetrics->getName(),
                        targetType: 'method',
                        value: $lines,
                        threshold: $attentionThreshold,
                        message: sprintf(
                            'Method %s::%s has %d lines (threshold: %d)',
                            $fileName,
                            $methodMetrics->getName(),
                            $lines,
                            $attentionThreshold,
                        ),
                        reasoning: 'Longer methods may be harder to understand and test. ' .
                            'Consider if parts could be extracted into smaller, focused methods.',
                    );
                } elseif ($lines >= $mildThreshold) {
                    $flags[] = new Flag(
                        ruleId: $this->getId(),
                        ruleName: $this->getName(),
                        level: FlagLevel::Mild,
                        target: $fileName . '::' . $methodMetrics->getName(),
                        targetType: 'method',
                        value: $lines,
                        threshold: $mildThreshold,
                        message: sprintf(
                            'Method %s::%s has %d lines (threshold: %d)',
                            $fileName,
                            $methodMetrics->getName(),
                            $lines,
                            $mildThreshold,
                        ),
                        reasoning: 'This method is moderately long. It might be fine as-is, ' .
                            'but could be worth a quick review.',
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
