<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics\Rules;

use CodeLens\Core\Heuristics\Flag;
use CodeLens\Core\Heuristics\FlagLevel;
use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Metrics\MetricsResult;

/**
 * Detects methods with many parameters.
 */
final class ManyParametersRule implements RuleInterface
{
    public function getId(): string
    {
        return 'many_parameters';
    }

    public function getName(): string
    {
        return 'Many Parameters';
    }

    public function getDescription(): string
    {
        return 'Identifies methods with many parameters that might benefit from parameter objects.';
    }

    public function apply(MetricsResult $metrics, HeuristicConfig $config): array
    {
        if (! $this->isEnabled($config)) {
            return [];
        }

        $flags = [];
        $mildThreshold = $config->getThreshold('parameter_count_mild', 5);
        $attentionThreshold = $config->getThreshold('parameter_count_attention', 8);

        foreach ($metrics->getFileMetrics() as $fileMetrics) {
            $fileName = basename($fileMetrics->getPath());

            foreach ($fileMetrics->getMethodMetrics() as $methodMetrics) {
                $count = $methodMetrics->getParameterCount();

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
                            'Method %s::%s has %d parameters (threshold: %d)',
                            $fileName,
                            $methodMetrics->getName(),
                            $count,
                            $attentionThreshold,
                        ),
                        reasoning: 'Many parameters can make a method harder to call and test. ' .
                            'Consider grouping related parameters into a value object or DTO.',
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
                            'Method %s::%s has %d parameters (threshold: %d)',
                            $fileName,
                            $methodMetrics->getName(),
                            $count,
                            $mildThreshold,
                        ),
                        reasoning: 'A moderate number of parameters. This might be fine, ' .
                            'especially for constructors with dependency injection.',
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
