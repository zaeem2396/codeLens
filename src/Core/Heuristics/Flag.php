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
final readonly class Flag
{
    public function __construct(
        public string $ruleId,
        public string $ruleName,
        public FlagLevel $level,
        public string $target,
        public string $targetType,
        public mixed $value,
        public mixed $threshold,
        public string $message,
        public string $reasoning,
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
