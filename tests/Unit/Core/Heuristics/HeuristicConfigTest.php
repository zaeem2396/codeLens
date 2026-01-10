<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core\Heuristics;

use CodeLens\Core\Heuristics\HeuristicConfig;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the HeuristicConfig class.
 */
class HeuristicConfigTest extends TestCase
{
    public function testDefaultThresholds(): void
    {
        $config = new HeuristicConfig();

        // Method length thresholds
        $this->assertEquals(50, $config->getThreshold('method_lines_mild'));
        $this->assertEquals(100, $config->getThreshold('method_lines_attention'));

        // Nesting depth thresholds
        $this->assertEquals(4, $config->getThreshold('nesting_depth_mild'));
        $this->assertEquals(6, $config->getThreshold('nesting_depth_attention'));

        // Parameter count thresholds
        $this->assertEquals(5, $config->getThreshold('parameter_count_mild'));
        $this->assertEquals(8, $config->getThreshold('parameter_count_attention'));
    }

    public function testCustomThresholds(): void
    {
        $config = new HeuristicConfig([], [
            'method_lines_mild' => 30,
            'method_lines_attention' => 60,
        ]);

        $this->assertEquals(30, $config->getThreshold('method_lines_mild'));
        $this->assertEquals(60, $config->getThreshold('method_lines_attention'));
    }

    public function testSetThreshold(): void
    {
        $config = new HeuristicConfig();
        $config->setThreshold('method_lines_mild', 25);

        $this->assertEquals(25, $config->getThreshold('method_lines_mild'));
    }

    public function testGetThresholdWithDefault(): void
    {
        $config = new HeuristicConfig();

        $this->assertNull($config->getThreshold('nonexistent'));
        $this->assertEquals('default', $config->getThreshold('nonexistent', 'default'));
    }

    public function testRuleEnabledByDefault(): void
    {
        $config = new HeuristicConfig();

        $this->assertTrue($config->isRuleEnabled('long_method'));
        $this->assertTrue($config->isRuleEnabled('deep_nesting'));
        $this->assertTrue($config->isRuleEnabled('any_rule'));
    }

    public function testDisableRule(): void
    {
        $config = new HeuristicConfig();
        $config->disableRule('long_method');

        $this->assertFalse($config->isRuleEnabled('long_method'));
        $this->assertTrue($config->isRuleEnabled('deep_nesting'));
    }

    public function testEnableRule(): void
    {
        $config = new HeuristicConfig(['long_method' => false]);

        $this->assertFalse($config->isRuleEnabled('long_method'));

        $config->enableRule('long_method');
        $this->assertTrue($config->isRuleEnabled('long_method'));
    }

    public function testGetAllThresholds(): void
    {
        $config = new HeuristicConfig();
        $thresholds = $config->getAllThresholds();

        $this->assertIsArray($thresholds);
        $this->assertArrayHasKey('method_lines_mild', $thresholds);
        $this->assertArrayHasKey('nesting_depth_attention', $thresholds);
    }

    public function testFromArray(): void
    {
        $config = HeuristicConfig::fromArray([
            'enabled_rules' => ['long_method' => false],
            'thresholds' => ['method_lines_mild' => 40],
        ]);

        $this->assertFalse($config->isRuleEnabled('long_method'));
        $this->assertEquals(40, $config->getThreshold('method_lines_mild'));
    }

    public function testToArray(): void
    {
        $config = new HeuristicConfig(['long_method' => false], ['method_lines_mild' => 40]);
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('enabled_rules', $array);
        $this->assertArrayHasKey('thresholds', $array);
        $this->assertFalse($array['enabled_rules']['long_method']);
    }

    public function testFluentInterface(): void
    {
        $config = new HeuristicConfig();

        $result = $config
            ->setThreshold('method_lines_mild', 30)
            ->disableRule('long_method')
            ->enableRule('deep_nesting');

        $this->assertInstanceOf(HeuristicConfig::class, $result);
        $this->assertEquals(30, $config->getThreshold('method_lines_mild'));
    }
}
