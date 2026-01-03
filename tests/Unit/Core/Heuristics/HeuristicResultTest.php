<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core\Heuristics;

use CodeLens\Core\Heuristics\Flag;
use CodeLens\Core\Heuristics\FlagLevel;
use CodeLens\Core\Heuristics\HeuristicResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the HeuristicResult class.
 */
class HeuristicResultTest extends TestCase
{
    private function createFlag(
        string $ruleId,
        FlagLevel $level,
        string $target = 'Test.php::method',
        string $targetType = 'method',
    ): Flag {
        return new Flag(
            ruleId: $ruleId,
            ruleName: ucfirst(str_replace('_', ' ', $ruleId)),
            level: $level,
            target: $target,
            targetType: $targetType,
            value: 100,
            threshold: 50,
            message: 'Test message',
            reasoning: 'Test reasoning',
        );
    }

    public function testEmptyResult(): void
    {
        $result = new HeuristicResult();

        $this->assertEquals(0, $result->getTotalCount());
        $this->assertEmpty($result->getFlags());
        $this->assertFalse($result->hasAttentionFlags());
    }

    public function testAddFlag(): void
    {
        $result = new HeuristicResult();
        $flag = $this->createFlag('long_method', FlagLevel::Attention);

        $result->addFlag($flag);

        $this->assertEquals(1, $result->getTotalCount());
        $this->assertCount(1, $result->getFlags());
    }

    public function testAddMultipleFlags(): void
    {
        $result = new HeuristicResult();

        $result->addFlags([
            $this->createFlag('long_method', FlagLevel::Attention),
            $this->createFlag('deep_nesting', FlagLevel::Mild),
            $this->createFlag('large_file', FlagLevel::Neutral),
        ]);

        $this->assertEquals(3, $result->getTotalCount());
    }

    public function testGetFlagsByLevel(): void
    {
        $result = new HeuristicResult();

        $result->addFlags([
            $this->createFlag('long_method', FlagLevel::Attention),
            $this->createFlag('deep_nesting', FlagLevel::Attention),
            $this->createFlag('many_parameters', FlagLevel::Mild),
        ]);

        $attentionFlags = $result->getFlagsByLevel(FlagLevel::Attention);
        $mildFlags = $result->getFlagsByLevel(FlagLevel::Mild);
        $neutralFlags = $result->getFlagsByLevel(FlagLevel::Neutral);

        $this->assertCount(2, $attentionFlags);
        $this->assertCount(1, $mildFlags);
        $this->assertCount(0, $neutralFlags);
    }

    public function testGetFlagsByRule(): void
    {
        $result = new HeuristicResult();

        $result->addFlags([
            $this->createFlag('long_method', FlagLevel::Attention),
            $this->createFlag('long_method', FlagLevel::Mild),
            $this->createFlag('deep_nesting', FlagLevel::Mild),
        ]);

        $longMethodFlags = $result->getFlagsByRule('long_method');
        $deepNestingFlags = $result->getFlagsByRule('deep_nesting');

        $this->assertCount(2, $longMethodFlags);
        $this->assertCount(1, $deepNestingFlags);
    }

    public function testGetFlagsByTargetType(): void
    {
        $result = new HeuristicResult();

        $result->addFlags([
            $this->createFlag('long_method', FlagLevel::Attention, 'Test.php::method', 'method'),
            $this->createFlag('large_file', FlagLevel::Mild, 'BigFile.php', 'file'),
            $this->createFlag('deep_nesting', FlagLevel::Mild, 'Another.php::test', 'method'),
        ]);

        $methodFlags = $result->getFlagsByTargetType('method');
        $fileFlags = $result->getFlagsByTargetType('file');

        $this->assertCount(2, $methodFlags);
        $this->assertCount(1, $fileFlags);
    }

    public function testCountByLevel(): void
    {
        $result = new HeuristicResult();

        $result->addFlags([
            $this->createFlag('rule1', FlagLevel::Attention),
            $this->createFlag('rule2', FlagLevel::Attention),
            $this->createFlag('rule3', FlagLevel::Mild),
        ]);

        $this->assertEquals(2, $result->getCountForLevel(FlagLevel::Attention));
        $this->assertEquals(1, $result->getCountForLevel(FlagLevel::Mild));
        $this->assertEquals(0, $result->getCountForLevel(FlagLevel::Neutral));

        $byLevel = $result->getCountByLevel();
        $this->assertArrayHasKey('attention', $byLevel);
        $this->assertArrayHasKey('mild', $byLevel);
    }

    public function testCountByRule(): void
    {
        $result = new HeuristicResult();

        $result->addFlags([
            $this->createFlag('long_method', FlagLevel::Attention),
            $this->createFlag('long_method', FlagLevel::Mild),
            $this->createFlag('deep_nesting', FlagLevel::Mild),
        ]);

        $byRule = $result->getCountByRule();

        $this->assertEquals(2, $byRule['long_method']);
        $this->assertEquals(1, $byRule['deep_nesting']);
    }

    public function testHasAttentionFlags(): void
    {
        $result = new HeuristicResult();
        $this->assertFalse($result->hasAttentionFlags());

        $result->addFlag($this->createFlag('mild_rule', FlagLevel::Mild));
        $this->assertFalse($result->hasAttentionFlags());

        $result->addFlag($this->createFlag('attention_rule', FlagLevel::Attention));
        $this->assertTrue($result->hasAttentionFlags());
    }

    public function testGetSummary(): void
    {
        $result = new HeuristicResult();

        $result->addFlags([
            $this->createFlag('long_method', FlagLevel::Attention),
            $this->createFlag('deep_nesting', FlagLevel::Mild),
        ]);

        $summary = $result->getSummary();

        $this->assertArrayHasKey('total', $summary);
        $this->assertArrayHasKey('by_level', $summary);
        $this->assertArrayHasKey('by_rule', $summary);
        $this->assertEquals(2, $summary['total']);
        $this->assertEquals(1, $summary['by_level']['attention']);
        $this->assertEquals(1, $summary['by_level']['mild']);
    }

    public function testToArray(): void
    {
        $result = new HeuristicResult();

        $result->addFlag($this->createFlag('long_method', FlagLevel::Attention));

        $array = $result->toArray();

        $this->assertArrayHasKey('flags', $array);
        $this->assertArrayHasKey('summary', $array);
        $this->assertCount(1, $array['flags']);
        $this->assertEquals('long_method', $array['flags'][0]['rule_id']);
    }
}


