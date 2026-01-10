<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core\Heuristics\Rules;

use CodeLens\Core\Heuristics\FlagLevel;
use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Heuristics\Rules\DeepNestingRule;
use CodeLens\Core\Heuristics\Rules\HighConditionalCountRule;
use CodeLens\Core\Heuristics\Rules\LargeFileRule;
use CodeLens\Core\Heuristics\Rules\LongMethodRule;
use CodeLens\Core\Heuristics\Rules\ManyParametersRule;
use CodeLens\Core\Heuristics\Rules\ManyReturnsRule;
use CodeLens\Core\Heuristics\Rules\MultipleClassesPerFileRule;
use CodeLens\Core\Metrics\FileMetrics;
use CodeLens\Core\Metrics\MethodMetrics;
use CodeLens\Core\Metrics\MetricsResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests for individual heuristic rules.
 */
class RulesTest extends TestCase
{
    private function createMethodMetrics(array $overrides = []): MethodMetrics
    {
        return new MethodMetrics(
            name: $overrides['name'] ?? 'testMethod',
            parentClass: $overrides['parentClass'] ?? 'TestClass',
            lineStart: $overrides['lineStart'] ?? 1,
            lineEnd: $overrides['lineEnd'] ?? 10,
            lineCount: $overrides['lineCount'] ?? 10,
            maxNestingDepth: $overrides['maxNestingDepth'] ?? 2,
            conditionalCount: $overrides['conditionalCount'] ?? 2,
            loopCount: $overrides['loopCount'] ?? 1,
            returnCount: $overrides['returnCount'] ?? 1,
            parameterCount: $overrides['parameterCount'] ?? 2,
            visibility: $overrides['visibility'] ?? 'public',
            isStatic: $overrides['isStatic'] ?? false,
            isAbstract: $overrides['isAbstract'] ?? false,
        );
    }

    private function createFileMetrics(array $overrides = [], array $methodMetrics = []): FileMetrics
    {
        return new FileMetrics(
            filePath: $overrides['filePath'] ?? '/path/to/TestClass.php',
            relativePath: $overrides['relativePath'] ?? 'TestClass.php',
            linesOfCode: $overrides['linesOfCode'] ?? 100,
            linesOfCodeWithoutComments: $overrides['linesOfCodeWithoutComments'] ?? 90,
            blankLines: $overrides['blankLines'] ?? 5,
            commentLines: $overrides['commentLines'] ?? 5,
            classCount: $overrides['classCount'] ?? 1,
            interfaceCount: $overrides['interfaceCount'] ?? 0,
            traitCount: $overrides['traitCount'] ?? 0,
            enumCount: $overrides['enumCount'] ?? 0,
            methodCount: $overrides['methodCount'] ?? 1,
            propertyCount: $overrides['propertyCount'] ?? 0,
            methodMetrics: $methodMetrics,
        );
    }

    private function createMetrics(array $fileMetrics): MetricsResult
    {
        return new MetricsResult($fileMetrics, 0.1);
    }

    // =====================
    // LongMethodRule Tests
    // =====================

    public function testLongMethodRuleMetadata(): void
    {
        $rule = new LongMethodRule();

        $this->assertEquals('long_method', $rule->getId());
        $this->assertEquals('Long Method', $rule->getName());
        $this->assertNotEmpty($rule->getDescription());
    }

    public function testLongMethodRuleNoFlags(): void
    {
        $rule = new LongMethodRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['lineCount' => 30]); // Below threshold
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertEmpty($flags);
    }

    public function testLongMethodRuleMildFlag(): void
    {
        $rule = new LongMethodRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['lineCount' => 60, 'lineEnd' => 60]); // Above mild (50)
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Mild, $flags[0]->level);
    }

    public function testLongMethodRuleAttentionFlag(): void
    {
        $rule = new LongMethodRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['lineCount' => 120, 'lineEnd' => 120]); // Above attention (100)
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Attention, $flags[0]->level);
    }

    public function testLongMethodRuleDisabled(): void
    {
        $rule = new LongMethodRule();
        $config = new HeuristicConfig();
        $config->disableRule('long_method');

        $method = $this->createMethodMetrics(['lineCount' => 150]);
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertEmpty($flags);
    }

    // =======================
    // DeepNestingRule Tests
    // =======================

    public function testDeepNestingRuleMetadata(): void
    {
        $rule = new DeepNestingRule();

        $this->assertEquals('deep_nesting', $rule->getId());
        $this->assertEquals('Deep Nesting', $rule->getName());
    }

    public function testDeepNestingRuleMildFlag(): void
    {
        $rule = new DeepNestingRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['maxNestingDepth' => 5]); // Above mild (4)
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Mild, $flags[0]->level);
    }

    public function testDeepNestingRuleAttentionFlag(): void
    {
        $rule = new DeepNestingRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['maxNestingDepth' => 7]); // Above attention (6)
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Attention, $flags[0]->level);
    }

    // ========================
    // ManyParametersRule Tests
    // ========================

    public function testManyParametersRuleMetadata(): void
    {
        $rule = new ManyParametersRule();

        $this->assertEquals('many_parameters', $rule->getId());
        $this->assertEquals('Many Parameters', $rule->getName());
    }

    public function testManyParametersRuleMildFlag(): void
    {
        $rule = new ManyParametersRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['parameterCount' => 6]); // Above mild (5)
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Mild, $flags[0]->level);
    }

    public function testManyParametersRuleAttentionFlag(): void
    {
        $rule = new ManyParametersRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['parameterCount' => 10]); // Above attention (8)
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Attention, $flags[0]->level);
    }

    // ==============================
    // HighConditionalCountRule Tests
    // ==============================

    public function testHighConditionalCountRuleMetadata(): void
    {
        $rule = new HighConditionalCountRule();

        $this->assertEquals('high_conditionals', $rule->getId());
        $this->assertEquals('High Conditional Count', $rule->getName());
    }

    public function testHighConditionalCountRuleMildFlag(): void
    {
        $rule = new HighConditionalCountRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['conditionalCount' => 12]); // Above mild (10)
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Mild, $flags[0]->level);
    }

    public function testHighConditionalCountRuleAttentionFlag(): void
    {
        $rule = new HighConditionalCountRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['conditionalCount' => 25]); // Above attention (20)
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Attention, $flags[0]->level);
    }

    // ===================
    // LargeFileRule Tests
    // ===================

    public function testLargeFileRuleMetadata(): void
    {
        $rule = new LargeFileRule();

        $this->assertEquals('large_file', $rule->getId());
        $this->assertEquals('Large File', $rule->getName());
    }

    public function testLargeFileRuleMildFlag(): void
    {
        $rule = new LargeFileRule();
        $config = new HeuristicConfig();

        $file = $this->createFileMetrics(['linesOfCode' => 350]); // Above mild (300)
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Mild, $flags[0]->level);
    }

    public function testLargeFileRuleAttentionFlag(): void
    {
        $rule = new LargeFileRule();
        $config = new HeuristicConfig();

        $file = $this->createFileMetrics(['linesOfCode' => 600]); // Above attention (500)
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Attention, $flags[0]->level);
    }

    // ================================
    // MultipleClassesPerFileRule Tests
    // ================================

    public function testMultipleClassesPerFileRuleMetadata(): void
    {
        $rule = new MultipleClassesPerFileRule();

        $this->assertEquals('multiple_classes', $rule->getId());
        $this->assertEquals('Multiple Classes Per File', $rule->getName());
    }

    public function testMultipleClassesPerFileRuleMildFlag(): void
    {
        $rule = new MultipleClassesPerFileRule();
        $config = new HeuristicConfig();

        $file = $this->createFileMetrics(['classCount' => 2]); // At mild threshold (2)
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Mild, $flags[0]->level);
    }

    public function testMultipleClassesPerFileRuleAttentionFlag(): void
    {
        $rule = new MultipleClassesPerFileRule();
        $config = new HeuristicConfig();

        $file = $this->createFileMetrics(['classCount' => 4]); // Above attention (3)
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Attention, $flags[0]->level);
    }

    // =====================
    // ManyReturnsRule Tests
    // =====================

    public function testManyReturnsRuleMetadata(): void
    {
        $rule = new ManyReturnsRule();

        $this->assertEquals('many_returns', $rule->getId());
        $this->assertEquals('Many Return Statements', $rule->getName());
    }

    public function testManyReturnsRuleMildFlag(): void
    {
        $rule = new ManyReturnsRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['returnCount' => 6]); // Above mild (5)
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Mild, $flags[0]->level);
    }

    public function testManyReturnsRuleAttentionFlag(): void
    {
        $rule = new ManyReturnsRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['returnCount' => 12]); // Above attention (10)
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertEquals(FlagLevel::Attention, $flags[0]->level);
    }

    // ====================
    // Message Format Tests
    // ====================

    public function testFlagMessageIncludesFileName(): void
    {
        $rule = new LongMethodRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['name' => 'processData', 'lineCount' => 120]);
        $file = $this->createFileMetrics(['filePath' => '/path/to/UserService.php'], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertStringContainsString('UserService.php', $flags[0]->message);
        $this->assertStringContainsString('processData', $flags[0]->message);
    }

    public function testFlagTargetIncludesFileName(): void
    {
        $rule = new DeepNestingRule();
        $config = new HeuristicConfig();

        $method = $this->createMethodMetrics(['name' => 'complexMethod', 'maxNestingDepth' => 7]);
        $file = $this->createFileMetrics(['filePath' => '/path/to/Controller.php'], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags = $rule->apply($metrics, $config);

        $this->assertCount(1, $flags);
        $this->assertStringContainsString('Controller.php', $flags[0]->target);
        $this->assertStringContainsString('complexMethod', $flags[0]->target);
    }

    // ========================
    // Custom Threshold Tests
    // ========================

    public function testCustomThresholdChangesDetection(): void
    {
        $rule = new LongMethodRule();

        // With default thresholds (50 mild), 40 lines should NOT trigger
        $config1 = new HeuristicConfig();
        $method = $this->createMethodMetrics(['lineCount' => 40]);
        $file = $this->createFileMetrics([], [$method]);
        $metrics = $this->createMetrics([$file]);

        $flags1 = $rule->apply($metrics, $config1);
        $this->assertEmpty($flags1);

        // With custom thresholds (30 mild), 40 lines SHOULD trigger
        $config2 = new HeuristicConfig([], ['method_lines_mild' => 30]);
        $flags2 = $rule->apply($metrics, $config2);
        $this->assertCount(1, $flags2);
        $this->assertEquals(FlagLevel::Mild, $flags2[0]->level);
    }
}
