<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core\Usage;

use CodeLens\Core\Usage\CallGraph;
use CodeLens\Core\Usage\CallReference;
use CodeLens\Core\Usage\CallType;
use CodeLens\Core\Usage\UsageResult;
use PHPUnit\Framework\TestCase;

class UsageResultTest extends TestCase
{
    private function createGraph(): CallGraph
    {
        $graph = new CallGraph();

        $graph->addReferences([
            new CallReference('A::m', 'B::m', CallType::MethodCall, '/test.php', 10),
            new CallReference('A::m', 'C::m', CallType::StaticCall, '/test.php', 20),
            new CallReference('X::m', 'B::m', CallType::MethodCall, '/test.php', 30),
        ]);

        return $graph;
    }

    public function testCreation(): void
    {
        $graph = $this->createGraph();

        $result = new UsageResult(
            callGraph: $graph,
            duration: 1.5,
            filesAnalyzed: 10,
            errors: ['file.php' => 'Parse error'],
        );

        $this->assertSame($graph, $result->callGraph);
        $this->assertSame(1.5, $result->duration);
        $this->assertSame(10, $result->filesAnalyzed);
        $this->assertCount(1, $result->errors);
    }

    public function testGetCallGraph(): void
    {
        $graph = $this->createGraph();
        $result = new UsageResult($graph, 0.5);

        $this->assertSame($graph, $result->getCallGraph());
    }

    public function testGetTotalReferences(): void
    {
        $graph = $this->createGraph();
        $result = new UsageResult($graph, 0.5);

        $this->assertSame(3, $result->getTotalReferences());
    }

    public function testGetUniqueCallers(): void
    {
        $graph = $this->createGraph();
        $result = new UsageResult($graph, 0.5);

        $this->assertSame(2, $result->getUniqueCallers());
    }

    public function testGetUniqueCallees(): void
    {
        $graph = $this->createGraph();
        $result = new UsageResult($graph, 0.5);

        $this->assertSame(2, $result->getUniqueCallees());
    }

    public function testGetEntryPointCount(): void
    {
        $graph = $this->createGraph();
        $result = new UsageResult($graph, 0.5);

        // A::m and X::m are entry points (no incoming calls)
        $this->assertSame(2, $result->getEntryPointCount());
    }

    public function testHasErrors(): void
    {
        $graph = $this->createGraph();

        $resultWithErrors = new UsageResult($graph, 0.5, 5, ['error' => 'message']);
        $resultWithoutErrors = new UsageResult($graph, 0.5, 5, []);

        $this->assertTrue($resultWithErrors->hasErrors());
        $this->assertFalse($resultWithoutErrors->hasErrors());
    }

    public function testGetErrorCount(): void
    {
        $graph = $this->createGraph();

        $result = new UsageResult($graph, 0.5, 5, [
            'file1.php' => 'error1',
            'file2.php' => 'error2',
        ]);

        $this->assertSame(2, $result->getErrorCount());
    }

    public function testGetFormattedDurationMilliseconds(): void
    {
        $graph = new CallGraph();

        $result = new UsageResult($graph, 0.5);

        $this->assertSame('500ms', $result->getFormattedDuration());
    }

    public function testGetFormattedDurationSeconds(): void
    {
        $graph = new CallGraph();

        $result = new UsageResult($graph, 2.567);

        $this->assertSame('2.57s', $result->getFormattedDuration());
    }

    public function testGetSummary(): void
    {
        $graph = $this->createGraph();
        $result = new UsageResult($graph, 1.0, 10, []);

        $summary = $result->getSummary();

        $this->assertSame(10, $summary['files_analyzed']);
        $this->assertSame(3, $summary['total_references']);
        $this->assertSame(2, $summary['unique_callers']);
        $this->assertSame(2, $summary['unique_callees']);
        $this->assertArrayHasKey('by_type', $summary);
        $this->assertArrayHasKey('duration', $summary);
        $this->assertSame(0, $summary['error_count']);
    }

    public function testGetCallersOf(): void
    {
        $graph = $this->createGraph();
        $result = new UsageResult($graph, 0.5);

        $callers = $result->getCallersOf('B::m');

        $this->assertCount(2, $callers);
    }

    public function testGetCallsFrom(): void
    {
        $graph = $this->createGraph();
        $result = new UsageResult($graph, 0.5);

        $calls = $result->getCallsFrom('A::m');

        $this->assertCount(2, $calls);
    }

    public function testGetClassUsage(): void
    {
        $graph = new CallGraph();
        $graph->addReferences([
            new CallReference('External::m', 'App\\Service::methodA', CallType::MethodCall, '/test.php', 10),
            new CallReference('App\\Service::methodA', 'App\\Repo::save', CallType::MethodCall, '/test.php', 20),
        ]);

        $result = new UsageResult($graph, 0.5);
        $usage = $result->getClassUsage('App\\Service');

        $this->assertArrayHasKey('incoming', $usage);
        $this->assertArrayHasKey('outgoing', $usage);
    }

    public function testToArray(): void
    {
        $graph = $this->createGraph();
        $result = new UsageResult($graph, 1.0, 5, ['file.php' => 'error']);

        $array = $result->toArray();

        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('call_graph', $array);
        $this->assertArrayHasKey('errors', $array);
    }

    public function testFromArray(): void
    {
        $data = [
            'summary' => [
                'files_analyzed' => 10,
            ],
            'call_graph' => [
                [
                    'caller_fqn' => 'A::m',
                    'callee_fqn' => 'B::m',
                    'call_type' => 'method_call',
                    'file' => '/test.php',
                    'line' => 10,
                    'confidence' => 1.0,
                    'context' => [],
                ],
            ],
            'errors' => [],
        ];

        $result = UsageResult::fromArray($data);

        $this->assertSame(10, $result->filesAnalyzed);
        $this->assertSame(1, $result->getTotalReferences());
    }
}
