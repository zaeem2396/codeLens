<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core\Heuristics;

use CodeLens\Core\Heuristics\FlagLevel;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the FlagLevel enum.
 */
class FlagLevelTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertEquals('neutral', FlagLevel::Neutral->value);
        $this->assertEquals('mild', FlagLevel::Mild->value);
        $this->assertEquals('attention', FlagLevel::Attention->value);
    }

    public function testLabels(): void
    {
        $this->assertEquals('Info', FlagLevel::Neutral->label());
        $this->assertEquals('Note', FlagLevel::Mild->label());
        $this->assertEquals('Review', FlagLevel::Attention->label());
    }

    public function testDescriptions(): void
    {
        $this->assertEquals('Informational only', FlagLevel::Neutral->description());
        $this->assertEquals('Might be worth a quick look', FlagLevel::Mild->description());
        $this->assertEquals('Worth reviewing when time permits', FlagLevel::Attention->description());
    }

    public function testFromValue(): void
    {
        $this->assertEquals(FlagLevel::Neutral, FlagLevel::from('neutral'));
        $this->assertEquals(FlagLevel::Mild, FlagLevel::from('mild'));
        $this->assertEquals(FlagLevel::Attention, FlagLevel::from('attention'));
    }

    public function testTryFromValidValue(): void
    {
        $this->assertEquals(FlagLevel::Attention, FlagLevel::tryFrom('attention'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(FlagLevel::tryFrom('invalid'));
    }
}


