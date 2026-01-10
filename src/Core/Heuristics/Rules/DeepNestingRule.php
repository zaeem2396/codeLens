<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics\Rules;

use CodeLens\Core\Heuristics\Flag;
use CodeLens\Core\Heuristics\FlagLevel;
use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Metrics\MetricsResult;

/**
 * Detects methods with deep nesting that may be complex to follow.
 */
final class DeepNestingRule implements RuleInterface
{
    public function getId(): string
    {
        return 'deep_nesting';
    }

    public function getName(): string
    {
        return 'Deep Nesting';
    }

    public function getDescription(): string
    {
        return 'Identifies methods with deeply nested control structures.';
    }

    public function apply(MetricsResult $metrics, HeuristicConfig $config): array
    {
        if (! $this->isEnabled($config)) {
            return [];
        }

        $flags = [];
        $mildThreshold = $config->getThreshold('nesting_depth_mild', 4);
        $attentionThreshold = $config->getThreshold('nesting_depth_attention', 6);

        foreach ($metrics->getFileMetrics() as $fileMetrics) {
            $fileName = basename($fileMetrics->getPath());

            foreach ($fileMetrics->getMethodMetrics() as $methodMetrics) {
                $depth = $methodMetrics->getMaxNestingDepth();

                if ($depth >= $attentionThreshold) {
                    $flags[] = new Flag(
                        ruleId: $this->getId(),
                        ruleName: $this->getName(),
                        level: FlagLevel::Attention,
                        target: $fileName . '::' . $methodMetrics->getName(),
                        targetType: 'method',
                        value: $depth,
                        threshold: $attentionThreshold,
                        message: sprintf(
                            'Method %s::%s has nesting depth of %d (threshold: %d)',
                            $fileName,
                            $methodMetrics->getName(),
                            $depth,
                            $attentionThreshold,
                        ),
                        reasoning: 'Deeply nested code can be hard to follow. Early returns, ' .
                            'guard clauses, or extracting nested logic might help readability.',
                    );
                } elseif ($depth >= $mildThreshold) {
                    $flags[] = new Flag(
                        ruleId: $this->getId(),
                        ruleName: $this->getName(),
                        level: FlagLevel::Mild,
                        target: $fileName . '::' . $methodMetrics->getName(),
                        targetType: 'method',
                        value: $depth,
                        threshold: $mildThreshold,
                        message: sprintf(
                            'Method %s::%s has nesting depth of %d (threshold: %d)',
                            $fileName,
                            $methodMetrics->getName(),
                            $depth,
                            $mildThreshold,
                        ),
                        reasoning: 'Moderate nesting depth. This might be appropriate for ' .
                            'the logic involved, but worth keeping an eye on.',
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
