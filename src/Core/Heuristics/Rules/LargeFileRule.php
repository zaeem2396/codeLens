<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics\Rules;

use CodeLens\Core\Heuristics\Flag;
use CodeLens\Core\Heuristics\FlagLevel;
use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Metrics\MetricsResult;

/**
 * Detects files with many lines of code.
 */
final class LargeFileRule implements RuleInterface
{
    public function getId(): string
    {
        return 'large_file';
    }

    public function getName(): string
    {
        return 'Large File';
    }

    public function getDescription(): string
    {
        return 'Identifies files with many lines that might benefit from being split.';
    }

    public function apply(MetricsResult $metrics, HeuristicConfig $config): array
    {
        if (! $this->isEnabled($config)) {
            return [];
        }

        $flags = [];
        $mildThreshold = $config->getThreshold('file_lines_mild', 300);
        $attentionThreshold = $config->getThreshold('file_lines_attention', 500);

        foreach ($metrics->getFileMetrics() as $fileMetrics) {
            $lines = $fileMetrics->getLines();
            $path = $fileMetrics->getPath();

            if ($lines >= $attentionThreshold) {
                $flags[] = new Flag(
                    ruleId: $this->getId(),
                    ruleName: $this->getName(),
                    level: FlagLevel::Attention,
                    target: $path,
                    targetType: 'file',
                    value: $lines,
                    threshold: $attentionThreshold,
                    message: sprintf(
                        'File %s has %d lines (threshold: %d)',
                        basename($path),
                        $lines,
                        $attentionThreshold,
                    ),
                    reasoning: 'Large files can be harder to navigate and understand. ' .
                        'Consider if there are natural boundaries where it could be split.',
                );
            } elseif ($lines >= $mildThreshold) {
                $flags[] = new Flag(
                    ruleId: $this->getId(),
                    ruleName: $this->getName(),
                    level: FlagLevel::Mild,
                    target: $path,
                    targetType: 'file',
                    value: $lines,
                    threshold: $mildThreshold,
                    message: sprintf(
                        'File %s has %d lines (threshold: %d)',
                        basename($path),
                        $lines,
                        $mildThreshold,
                    ),
                    reasoning: 'A moderately sized file. This is often fine, ' .
                        'especially for feature-complete classes.',
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
