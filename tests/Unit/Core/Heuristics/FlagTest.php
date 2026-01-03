<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core\Heuristics;

use CodeLens\Core\Heuristics\Flag;
use CodeLens\Core\Heuristics\FlagLevel;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Flag class.
 */
class FlagTest extends TestCase
{
    public function testFlagCreation(): void
    {
        $flag = new Flag(
            ruleId: 'long_method',
            ruleName: 'Long Method',
            level: FlagLevel::Attention,
            target: 'UserService.php::processData',
            targetType: 'method',
            value: 150,
            threshold: 100,
            message: 'Method UserService.php::processData has 150 lines (threshold: 100)',
            reasoning: 'Longer methods may be harder to understand.',
        );

        $this->assertEquals('long_method', $flag->ruleId);
        $this->assertEquals('Long Method', $flag->ruleName);
        $this->assertEquals(FlagLevel::Attention, $flag->level);
        $this->assertEquals('UserService.php::processData', $flag->target);
        $this->assertEquals('method', $flag->targetType);
        $this->assertEquals(150, $flag->value);
        $this->assertEquals(100, $flag->threshold);
        $this->assertStringContainsString('150 lines', $flag->message);
        $this->assertStringContainsString('harder to understand', $flag->reasoning);
    }

    public function testToArray(): void
    {
        $flag = new Flag(
            ruleId: 'deep_nesting',
            ruleName: 'Deep Nesting',
            level: FlagLevel::Mild,
            target: 'Controller.php::index',
            targetType: 'method',
            value: 5,
            threshold: 4,
            message: 'Method has nesting depth of 5',
            reasoning: 'Deep nesting can be hard to follow.',
        );

        $array = $flag->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('deep_nesting', $array['rule_id']);
        $this->assertEquals('Deep Nesting', $array['rule_name']);
        $this->assertEquals('mild', $array['level']);
        $this->assertEquals('Note', $array['level_label']);
        $this->assertEquals('Controller.php::index', $array['target']);
        $this->assertEquals('method', $array['target_type']);
        $this->assertEquals(5, $array['value']);
        $this->assertEquals(4, $array['threshold']);
    }

    public function testFromArray(): void
    {
        $data = [
            'rule_id' => 'large_file',
            'rule_name' => 'Large File',
            'level' => 'attention',
            'target' => 'BigFile.php',
            'target_type' => 'file',
            'value' => 500,
            'threshold' => 300,
            'message' => 'File has 500 lines',
            'reasoning' => 'Large files can be harder to navigate.',
        ];

        $flag = Flag::fromArray($data);

        $this->assertEquals('large_file', $flag->ruleId);
        $this->assertEquals('Large File', $flag->ruleName);
        $this->assertEquals(FlagLevel::Attention, $flag->level);
        $this->assertEquals('BigFile.php', $flag->target);
        $this->assertEquals('file', $flag->targetType);
        $this->assertEquals(500, $flag->value);
        $this->assertEquals(300, $flag->threshold);
    }

    public function testRoundTrip(): void
    {
        $original = new Flag(
            ruleId: 'many_parameters',
            ruleName: 'Many Parameters',
            level: FlagLevel::Attention,
            target: 'Service.php::create',
            targetType: 'method',
            value: 10,
            threshold: 8,
            message: 'Method has 10 parameters',
            reasoning: 'Many parameters can make testing harder.',
        );

        $array = $original->toArray();
        $restored = Flag::fromArray($array);

        $this->assertEquals($original->ruleId, $restored->ruleId);
        $this->assertEquals($original->ruleName, $restored->ruleName);
        $this->assertEquals($original->level, $restored->level);
        $this->assertEquals($original->target, $restored->target);
        $this->assertEquals($original->value, $restored->value);
        $this->assertEquals($original->threshold, $restored->threshold);
    }
}
