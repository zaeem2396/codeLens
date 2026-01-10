<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core\Usage;

use CodeLens\Core\Usage\CallGraph;
use CodeLens\Core\Usage\CallReference;
use CodeLens\Core\Usage\CallType;
use PHPUnit\Framework\TestCase;

class CallGraphTest extends TestCase
{
    private function createReference(
        string $caller,
        string $callee,
        CallType $type = CallType::MethodCall,
        float $confidence = 1.0,
    ): CallReference {
        return new CallReference(
            callerFqn: $caller,
            calleeFqn: $callee,
            callType: $type,
            file: '/test.php',
            line: 10,
            confidence: $confidence,
        );
    }

    public function testAddReference(): void
    {
        $graph = new CallGraph();
        $ref = $this->createReference('A::b', 'C::d');

        $graph->addReference($ref);

        $this->assertSame(1, $graph->count());
        $this->assertCount(1, $graph->getAllReferences());
    }

    public function testAddMultipleReferences(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('A::b', 'C::d'),
            $this->createReference('A::b', 'E::f'),
            $this->createReference('X::y', 'C::d'),
        ]);

        $this->assertSame(3, $graph->count());
    }

    public function testGetOutgoingCalls(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('A::method', 'B::call'),
            $this->createReference('A::method', 'C::call'),
            $this->createReference('X::method', 'Y::call'),
        ]);

        $outgoing = $graph->getOutgoingCalls('A::method');

        $this->assertCount(2, $outgoing);
    }

    public function testGetIncomingCalls(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('A::m', 'Target::method'),
            $this->createReference('B::m', 'Target::method'),
            $this->createReference('C::m', 'Target::method'),
            $this->createReference('X::m', 'Other::method'),
        ]);

        $incoming = $graph->getIncomingCalls('Target::method');

        $this->assertCount(3, $incoming);
    }

    public function testGetOutgoingCallsForClass(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('App\\Service::methodA', 'App\\Repo::save'),
            $this->createReference('App\\Service::methodB', 'App\\Repo::find'),
            $this->createReference('Other\\Class::method', 'App\\Repo::all'),
        ]);

        $outgoing = $graph->getOutgoingCallsForClass('App\\Service');

        $this->assertCount(2, $outgoing);
    }

    public function testGetIncomingCallsForClass(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('X::a', 'App\\Target::methodA'),
            $this->createReference('Y::b', 'App\\Target::methodB'),
            $this->createReference('Z::c', 'App\\Target', CallType::NewInstance),
            $this->createReference('W::d', 'Other\\Class::method'),
        ]);

        $incoming = $graph->getIncomingCallsForClass('App\\Target');

        $this->assertCount(3, $incoming);
    }

    public function testGetCallers(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('A::m', 'X::x'),
            $this->createReference('B::m', 'Y::y'),
            $this->createReference('C::m', 'Z::z'),
        ]);

        $callers = $graph->getCallers();

        $this->assertCount(3, $callers);
        $this->assertContains('A::m', $callers);
        $this->assertContains('B::m', $callers);
        $this->assertContains('C::m', $callers);
    }

    public function testGetCallees(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('A::m', 'X::x'),
            $this->createReference('B::m', 'Y::y'),
            $this->createReference('C::m', 'X::x'),
        ]);

        $callees = $graph->getCallees();

        $this->assertCount(2, $callees);
        $this->assertContains('X::x', $callees);
        $this->assertContains('Y::y', $callees);
    }

    public function testGetEntryPoints(): void
    {
        $graph = new CallGraph();

        // A calls B, B calls C
        // A is entry point (nothing calls A)
        $graph->addReferences([
            $this->createReference('A::start', 'B::process'),
            $this->createReference('B::process', 'C::finish'),
        ]);

        $entryPoints = $graph->getEntryPoints();

        $this->assertCount(1, $entryPoints);
        $this->assertContains('A::start', $entryPoints);
    }

    public function testHasIncomingCalls(): void
    {
        $graph = new CallGraph();

        $graph->addReference($this->createReference('A::m', 'B::m'));

        $this->assertTrue($graph->hasIncomingCalls('B::m'));
        $this->assertFalse($graph->hasIncomingCalls('A::m'));
        $this->assertFalse($graph->hasIncomingCalls('Unknown::m'));
    }

    public function testHasOutgoingCalls(): void
    {
        $graph = new CallGraph();

        $graph->addReference($this->createReference('A::m', 'B::m'));

        $this->assertTrue($graph->hasOutgoingCalls('A::m'));
        $this->assertFalse($graph->hasOutgoingCalls('B::m'));
        $this->assertFalse($graph->hasOutgoingCalls('Unknown::m'));
    }

    public function testGetByCallType(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('A::m', 'B::m', CallType::MethodCall),
            $this->createReference('A::m', 'C::m', CallType::StaticCall),
            $this->createReference('A::m', 'D', CallType::NewInstance),
        ]);

        $methodCalls = $graph->getByCallType(CallType::MethodCall);
        $staticCalls = $graph->getByCallType(CallType::StaticCall);
        $newInstances = $graph->getByCallType(CallType::NewInstance);

        $this->assertCount(1, $methodCalls);
        $this->assertCount(1, $staticCalls);
        $this->assertCount(1, $newInstances);
    }

    public function testGetByFile(): void
    {
        $graph = new CallGraph();

        $ref1 = new CallReference('A::m', 'B::m', CallType::MethodCall, '/file1.php', 10);
        $ref2 = new CallReference('C::m', 'D::m', CallType::MethodCall, '/file1.php', 20);
        $ref3 = new CallReference('E::m', 'F::m', CallType::MethodCall, '/file2.php', 10);

        $graph->addReferences([$ref1, $ref2, $ref3]);

        $file1Refs = $graph->getByFile('/file1.php');
        $file2Refs = $graph->getByFile('/file2.php');

        $this->assertCount(2, $file1Refs);
        $this->assertCount(1, $file2Refs);
    }

    public function testGetHighConfidenceReferences(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('A::m', 'B::m', CallType::MethodCall, 1.0),
            $this->createReference('C::m', 'D::m', CallType::MethodCall, 0.95),
            $this->createReference('E::m', 'F::m', CallType::MethodCall, 0.5),
        ]);

        $highConfidence = $graph->getHighConfidenceReferences();

        $this->assertCount(2, $highConfidence);
    }

    public function testClear(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('A::m', 'B::m'),
            $this->createReference('C::m', 'D::m'),
        ]);

        $this->assertSame(2, $graph->count());

        $graph->clear();

        $this->assertSame(0, $graph->count());
        $this->assertEmpty($graph->getCallers());
        $this->assertEmpty($graph->getCallees());
    }

    public function testMerge(): void
    {
        $graph1 = new CallGraph();
        $graph1->addReference($this->createReference('A::m', 'B::m'));

        $graph2 = new CallGraph();
        $graph2->addReference($this->createReference('C::m', 'D::m'));

        $graph1->merge($graph2);

        $this->assertSame(2, $graph1->count());
    }

    public function testGetStats(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('A::m', 'B::m', CallType::MethodCall),
            $this->createReference('A::m', 'C::m', CallType::StaticCall),
            $this->createReference('X::m', 'B::m', CallType::MethodCall),
        ]);

        $stats = $graph->getStats();

        $this->assertSame(3, $stats['total_references']);
        $this->assertSame(2, $stats['unique_callers']);
        $this->assertSame(2, $stats['unique_callees']);
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertSame(2, $stats['by_type']['method_call']);
        $this->assertSame(1, $stats['by_type']['static_call']);
    }

    public function testToArrayAndFromArray(): void
    {
        $graph = new CallGraph();

        $graph->addReferences([
            $this->createReference('A::m', 'B::m'),
            $this->createReference('C::m', 'D::m'),
        ]);

        $array = $graph->toArray();
        $restored = CallGraph::fromArray($array);

        $this->assertSame($graph->count(), $restored->count());
        $this->assertCount(count($graph->getCallers()), $restored->getCallers());
        $this->assertCount(count($graph->getCallees()), $restored->getCallees());
    }
}
