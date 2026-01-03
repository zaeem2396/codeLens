<?php

declare(strict_types=1);

namespace CodeLens\Core\Heuristics;

/**
 * Severity levels for heuristic flags.
 *
 * These are NOT judgments - they indicate attention levels:
 * - Neutral: Informational, no action suggested
 * - Mild: Might be worth a quick look
 * - Attention: Worth reviewing when time permits
 */
enum FlagLevel: string
{
    case Neutral = 'neutral';
    case Mild = 'mild';
    case Attention = 'attention';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Neutral => 'Info',
            self::Mild => 'Note',
            self::Attention => 'Review',
        };
    }

    /**
     * Get description of what this level means.
     */
    public function description(): string
    {
        return match ($this) {
            self::Neutral => 'Informational only',
            self::Mild => 'Might be worth a quick look',
            self::Attention => 'Worth reviewing when time permits',
        };
    }
}
