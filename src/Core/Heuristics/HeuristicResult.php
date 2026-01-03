<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics;

/**
 * Aggregates all flags from heuristic analysis.
 */
final class HeuristicResult
{
    /** @var Flag[] */
    private array $flags = [];

    /** @var array<string, int> Count by level */
    private array $countByLevel = [];

    /** @var array<string, int> Count by rule */
    private array $countByRule = [];

    /**
     * Add a flag to the results.
     */
    public function addFlag(Flag $flag): void
    {
        $this->flags[] = $flag;

        // Update level counts
        $level = $flag->level->value;
        $this->countByLevel[$level] = ($this->countByLevel[$level] ?? 0) + 1;

        // Update rule counts
        $ruleId = $flag->ruleId;
        $this->countByRule[$ruleId] = ($this->countByRule[$ruleId] ?? 0) + 1;
    }

    /**
     * Add multiple flags.
     *
     * @param Flag[] $flags
     */
    public function addFlags(array $flags): void
    {
        foreach ($flags as $flag) {
            $this->addFlag($flag);
        }
    }

    /**
     * Get all flags.
     *
     * @return Flag[]
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Get flags filtered by level.
     *
     * @return Flag[]
     */
    public function getFlagsByLevel(FlagLevel $level): array
    {
        return array_filter(
            $this->flags,
            fn (Flag $flag) => $flag->level === $level,
        );
    }

    /**
     * Get flags filtered by rule ID.
     *
     * @return Flag[]
     */
    public function getFlagsByRule(string $ruleId): array
    {
        return array_filter(
            $this->flags,
            fn (Flag $flag) => $flag->ruleId === $ruleId,
        );
    }

    /**
     * Get flags filtered by target type.
     *
     * @return Flag[]
     */
    public function getFlagsByTargetType(string $targetType): array
    {
        return array_filter(
            $this->flags,
            fn (Flag $flag) => $flag->targetType === $targetType,
        );
    }

    /**
     * Get total flag count.
     */
    public function getTotalCount(): int
    {
        return count($this->flags);
    }

    /**
     * Get count by level.
     */
    public function getCountByLevel(): array
    {
        return $this->countByLevel;
    }

    /**
     * Get count for a specific level.
     */
    public function getCountForLevel(FlagLevel $level): int
    {
        return $this->countByLevel[$level->value] ?? 0;
    }

    /**
     * Get count by rule.
     */
    public function getCountByRule(): array
    {
        return $this->countByRule;
    }

    /**
     * Check if there are any flags at attention level.
     */
    public function hasAttentionFlags(): bool
    {
        return $this->getCountForLevel(FlagLevel::Attention) > 0;
    }

    /**
     * Get a summary of the results.
     */
    public function getSummary(): array
    {
        return [
            'total' => $this->getTotalCount(),
            'by_level' => [
                'attention' => $this->getCountForLevel(FlagLevel::Attention),
                'mild' => $this->getCountForLevel(FlagLevel::Mild),
                'neutral' => $this->getCountForLevel(FlagLevel::Neutral),
            ],
            'by_rule' => $this->countByRule,
        ];
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'flags' => array_map(fn (Flag $f) => $f->toArray(), $this->flags),
            'summary' => $this->getSummary(),
        ];
    }
}
