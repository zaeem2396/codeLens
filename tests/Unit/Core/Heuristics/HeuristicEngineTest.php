<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core\Heuristics;

use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Heuristics\HeuristicEngine;
use CodeLens\Core\Heuristics\HeuristicResult;
use CodeLens\Core\Heuristics\Rules\RuleInterface;
use CodeLens\Core\Metrics\FileMetrics;
use CodeLens\Core\Metrics\MethodMetrics;
use CodeLens\Core\Metrics\MetricsResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the HeuristicEngine class.
 */
class HeuristicEngineTest extends TestCase
{
    public function testDefaultRulesRegistered(): void
    {
        $engine = new HeuristicEngine();
        $rules = $engine->getRules();

        $this->assertNotEmpty($rules);
        $this->assertGreaterThanOrEqual(7, count($rules));

        // Check specific rules are registered
        $ruleIds = array_map(fn ($r) => $r->getId(), $rules);
        $this->assertContains('long_method', $ruleIds);
        $this->assertContains('deep_nesting', $ruleIds);
        $this->assertContains('many_parameters', $ruleIds);
        $this->assertContains('high_conditionals', $ruleIds);
        $this->assertContains('large_file', $ruleIds);
        $this->assertContains('multiple_classes', $ruleIds);
        $this->assertContains('many_returns', $ruleIds);
    }

    public function testCustomConfig(): void
    {
        $config = new HeuristicConfig();
        $config->setThreshold('method_lines_mild', 25);

        $engine = new HeuristicEngine($config);

        $this->assertEquals(25, $engine->getConfig()->getThreshold('method_lines_mild'));
    }

    public function testRegisterRule(): void
    {
        $engine = new HeuristicEngine();
        $initialCount = count($engine->getRules());

        $mockRule = $this->createMock(RuleInterface::class);
        $mockRule->method('getId')->willReturn('custom_rule');

        $engine->registerRule($mockRule);

        $this->assertCount($initialCount + 1, $engine->getRules());
    }

    public function testRemoveRule(): void
    {
        $engine = new HeuristicEngine();
        $initialCount = count($engine->getRules());

        $engine->removeRule('long_method');

        $this->assertCount($initialCount - 1, $engine->getRules());

        $ruleIds = array_map(fn ($r) => $r->getId(), $engine->getRules());
        $this->assertNotContains('long_method', $ruleIds);
    }

    public function testGetRulesSummary(): void
    {
        $engine = new HeuristicEngine();
        $summary = $engine->getRulesSummary();

        $this->assertNotEmpty($summary);
        $this->assertIsArray($summary);

        $firstRule = $summary[0];
        $this->assertArrayHasKey('id', $firstRule);
        $this->assertArrayHasKey('name', $firstRule);
        $this->assertArrayHasKey('description', $firstRule);
        $this->assertArrayHasKey('enabled', $firstRule);
    }

    public function testAnalyzeWithEmptyMetrics(): void
    {
        $engine = new HeuristicEngine();
        $metrics = new MetricsResult([], 0.1);

        $result = $engine->analyze($metrics);

        $this->assertInstanceOf(HeuristicResult::class, $result);
        $this->assertEquals(0, $result->getTotalCount());
    }

    public function testAnalyzeDetectsLongMethod(): void
    {
        $config = new HeuristicConfig();
        $config->setThreshold('method_lines_attention', 100);

        $engine = new HeuristicEngine($config);

        $methodMetrics = new MethodMetrics(
            name: 'longMethod',
            parentClass: 'TestClass',
            lineStart: 1,
            lineEnd: 150,
            lineCount: 150,
            maxNestingDepth: 2,
            conditionalCount: 5,
            loopCount: 2,
            returnCount: 3,
            parameterCount: 2,
            visibility: 'public',
            isStatic: false,
            isAbstract: false,
        );

        $fileMetrics = new FileMetrics(
            filePath: '/path/to/TestClass.php',
            relativePath: 'TestClass.php',
            linesOfCode: 200,
            linesOfCodeWithoutComments: 180,
            blankLines: 10,
            commentLines: 10,
            classCount: 1,
            interfaceCount: 0,
            traitCount: 0,
            enumCount: 0,
            methodCount: 1,
            propertyCount: 0,
            methodMetrics: [$methodMetrics],
        );

        $metrics = new MetricsResult([$fileMetrics], 0.1);
        $result = $engine->analyze($metrics);

        // Should detect long method (150 lines > 100 threshold)
        $longMethodFlags = $result->getFlagsByRule('long_method');
        $this->assertNotEmpty($longMethodFlags);
    }

    public function testAnalyzeWithDisabledRule(): void
    {
        $config = new HeuristicConfig();
        $config->disableRule('long_method');

        $engine = new HeuristicEngine($config);

        $methodMetrics = new MethodMetrics(
            name: 'longMethod',
            parentClass: 'TestClass',
            lineStart: 1,
            lineEnd: 150,
            lineCount: 150,
            maxNestingDepth: 2,
            conditionalCount: 5,
            loopCount: 2,
            returnCount: 3,
            parameterCount: 2,
            visibility: 'public',
            isStatic: false,
            isAbstract: false,
        );

        $fileMetrics = new FileMetrics(
            filePath: '/path/to/TestClass.php',
            relativePath: 'TestClass.php',
            linesOfCode: 200,
            linesOfCodeWithoutComments: 180,
            blankLines: 10,
            commentLines: 10,
            classCount: 1,
            interfaceCount: 0,
            traitCount: 0,
            enumCount: 0,
            methodCount: 1,
            propertyCount: 0,
            methodMetrics: [$methodMetrics],
        );

        $metrics = new MetricsResult([$fileMetrics], 0.1);
        $result = $engine->analyze($metrics);

        // Should NOT detect long method because rule is disabled
        $longMethodFlags = $result->getFlagsByRule('long_method');
        $this->assertEmpty($longMethodFlags);
    }

    public function testApplySpecificRule(): void
    {
        $engine = new HeuristicEngine();

        $methodMetrics = new MethodMetrics(
            name: 'testMethod',
            parentClass: 'TestClass',
            lineStart: 1,
            lineEnd: 150,
            lineCount: 150,
            maxNestingDepth: 8,
            conditionalCount: 5,
            loopCount: 2,
            returnCount: 3,
            parameterCount: 2,
            visibility: 'public',
            isStatic: false,
            isAbstract: false,
        );

        $fileMetrics = new FileMetrics(
            filePath: '/path/to/TestClass.php',
            relativePath: 'TestClass.php',
            linesOfCode: 200,
            linesOfCodeWithoutComments: 180,
            blankLines: 10,
            commentLines: 10,
            classCount: 1,
            interfaceCount: 0,
            traitCount: 0,
            enumCount: 0,
            methodCount: 1,
            propertyCount: 0,
            methodMetrics: [$methodMetrics],
        );

        $metrics = new MetricsResult([$fileMetrics], 0.1);

        // Apply only deep_nesting rule
        $result = $engine->applyRule('deep_nesting', $metrics);

        // Should only have deep nesting flags
        $this->assertNotEmpty($result->getFlagsByRule('deep_nesting'));
        $this->assertEmpty($result->getFlagsByRule('long_method'));
    }
}


