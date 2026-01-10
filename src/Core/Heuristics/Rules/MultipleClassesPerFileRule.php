<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics\Rules;

use CodeLens\Core\Heuristics\Flag;
use CodeLens\Core\Heuristics\FlagLevel;
use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Metrics\MetricsResult;

/**
 * Detects files with multiple class definitions.
 */
final class MultipleClassesPerFileRule implements RuleInterface
{
    public function getId(): string
    {
        return 'multiple_classes';
    }

    public function getName(): string
    {
        return 'Multiple Classes Per File';
    }

    public function getDescription(): string
    {
        return 'Identifies files with multiple class definitions.';
    }

    public function apply(MetricsResult $metrics, HeuristicConfig $config): array
    {
        if (! $this->isEnabled($config)) {
            return [];
        }

        $flags = [];
        $mildThreshold = $config->getThreshold('classes_per_file_mild', 2);
        $attentionThreshold = $config->getThreshold('classes_per_file_attention', 3);

        foreach ($metrics->getFileMetrics() as $fileMetrics) {
            $count = $fileMetrics->getClassCount();
            $path = $fileMetrics->getPath();

            if ($count >= $attentionThreshold) {
                $flags[] = new Flag(
                    ruleId: $this->getId(),
                    ruleName: $this->getName(),
                    level: FlagLevel::Attention,
                    target: $path,
                    targetType: 'file',
                    value: $count,
                    threshold: $attentionThreshold,
                    message: sprintf(
                        'File %s has %d classes (threshold: %d)',
                        basename($path),
                        $count,
                        $attentionThreshold,
                    ),
                    reasoning: 'Multiple classes in one file can make navigation harder. ' .
                        'Consider moving each class to its own file for PSR-4 compliance.',
                );
            } elseif ($count >= $mildThreshold) {
                $flags[] = new Flag(
                    ruleId: $this->getId(),
                    ruleName: $this->getName(),
                    level: FlagLevel::Mild,
                    target: $path,
                    targetType: 'file',
                    value: $count,
                    threshold: $mildThreshold,
                    message: sprintf(
                        'File %s has %d classes (threshold: %d)',
                        basename($path),
                        $count,
                        $mildThreshold,
                    ),
                    reasoning: 'Having a few closely related classes together is sometimes intentional. ' .
                        'Just be aware of PSR-4 conventions.',
                );
            }
        }

        return $flags;
    }

    public function isEnabled(HeuristicConfig $config): bool
    {
        return $config->isRuleEnabled($this->getId());
    }
}
