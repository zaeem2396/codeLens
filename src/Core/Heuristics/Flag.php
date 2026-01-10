<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics;

/**
 * Represents a single heuristic flag.
 *
 * Each flag includes:
 * - What was detected
 * - The current value
 * - The threshold that triggered it
 * - An explanation of why it matters
 * - Soft, non-judgmental language
 */
final class Flag
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $ruleName,
        public readonly FlagLevel $level,
        public readonly string $target,
        public readonly string $targetType,
        public readonly mixed $value,
        public readonly mixed $threshold,
        public readonly string $message,
        public readonly string $reasoning,
    ) {
    }

    /**
     * Convert to array for storage/display.
     */
    public function toArray(): array
    {
        return [
            'rule_id' => $this->ruleId,
            'rule_name' => $this->ruleName,
            'level' => $this->level->value,
            'level_label' => $this->level->label(),
            'target' => $this->target,
            'target_type' => $this->targetType,
            'value' => $this->value,
            'threshold' => $this->threshold,
            'message' => $this->message,
            'reasoning' => $this->reasoning,
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ruleId: $data['rule_id'],
            ruleName: $data['rule_name'],
            level: FlagLevel::from($data['level']),
            target: $data['target'],
            targetType: $data['target_type'],
            value: $data['value'],
            threshold: $data['threshold'],
            message: $data['message'],
            reasoning: $data['reasoning'],
        );
    }
}
