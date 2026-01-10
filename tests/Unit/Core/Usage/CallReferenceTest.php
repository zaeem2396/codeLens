<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core\Usage;

use CodeLens\Core\Usage\CallReference;
use CodeLens\Core\Usage\CallType;
use PHPUnit\Framework\TestCase;

class CallReferenceTest extends TestCase
{
    public function testCreation(): void
    {
        $ref = new CallReference(
            callerFqn: 'App\\Service\\UserService::create',
            calleeFqn: 'App\\Repository\\UserRepository::save',
            callType: CallType::MethodCall,
            file: '/app/src/Service/UserService.php',
            line: 42,
            confidence: 1.0,
            context: ['foo' => 'bar'],
        );

        $this->assertSame('App\\Service\\UserService::create', $ref->callerFqn);
        $this->assertSame('App\\Repository\\UserRepository::save', $ref->calleeFqn);
        $this->assertSame(CallType::MethodCall, $ref->callType);
        $this->assertSame('/app/src/Service/UserService.php', $ref->file);
        $this->assertSame(42, $ref->line);
        $this->assertSame(1.0, $ref->confidence);
        $this->assertSame(['foo' => 'bar'], $ref->context);
    }

    public function testGetCallerClass(): void
    {
        $ref = new CallReference(
            callerFqn: 'App\\Service\\UserService::create',
            calleeFqn: 'App\\Repository\\UserRepository::save',
            callType: CallType::MethodCall,
            file: '/app/file.php',
            line: 10,
        );

        $this->assertSame('App\\Service\\UserService', $ref->getCallerClass());
    }

    public function testGetCallerMethod(): void
    {
        $ref = new CallReference(
            callerFqn: 'App\\Service\\UserService::create',
            calleeFqn: 'App\\Repository\\UserRepository::save',
            callType: CallType::MethodCall,
            file: '/app/file.php',
            line: 10,
        );

        $this->assertSame('create', $ref->getCallerMethod());
    }

    public function testGetCalleeClass(): void
    {
        $ref = new CallReference(
            callerFqn: 'App\\Service\\UserService::create',
            calleeFqn: 'App\\Repository\\UserRepository::save',
            callType: CallType::MethodCall,
            file: '/app/file.php',
            line: 10,
        );

        $this->assertSame('App\\Repository\\UserRepository', $ref->getCalleeClass());
    }

    public function testGetCalleeClassForNewInstance(): void
    {
        $ref = new CallReference(
            callerFqn: 'App\\Service\\UserService::create',
            calleeFqn: 'App\\Entity\\User',
            callType: CallType::NewInstance,
            file: '/app/file.php',
            line: 10,
        );

        $this->assertSame('App\\Entity\\User', $ref->getCalleeClass());
        $this->assertSame('__construct', $ref->getCalleeMethod());
    }

    public function testIsHighConfidence(): void
    {
        $highConfidence = new CallReference(
            callerFqn: 'A::b',
            calleeFqn: 'C::d',
            callType: CallType::MethodCall,
            file: '/app/file.php',
            line: 10,
            confidence: 0.95,
        );

        $lowConfidence = new CallReference(
            callerFqn: 'A::b',
            calleeFqn: 'C::d',
            callType: CallType::MethodCall,
            file: '/app/file.php',
            line: 10,
            confidence: 0.5,
        );

        $this->assertTrue($highConfidence->isHighConfidence());
        $this->assertFalse($lowConfidence->isHighConfidence());
    }

    public function testIsSelfReference(): void
    {
        $selfRef = new CallReference(
            callerFqn: 'App\\Service::methodA',
            calleeFqn: 'App\\Service::methodB',
            callType: CallType::MethodCall,
            file: '/app/file.php',
            line: 10,
        );

        $otherRef = new CallReference(
            callerFqn: 'App\\ServiceA::method',
            calleeFqn: 'App\\ServiceB::method',
            callType: CallType::MethodCall,
            file: '/app/file.php',
            line: 10,
        );

        $this->assertTrue($selfRef->isSelfReference());
        $this->assertFalse($otherRef->isSelfReference());
    }

    public function testToArray(): void
    {
        $ref = new CallReference(
            callerFqn: 'A::b',
            calleeFqn: 'C::d',
            callType: CallType::StaticCall,
            file: '/app/file.php',
            line: 42,
            confidence: 0.8,
            context: ['key' => 'value'],
        );

        $array = $ref->toArray();

        $this->assertSame('A::b', $array['caller_fqn']);
        $this->assertSame('C::d', $array['callee_fqn']);
        $this->assertSame('static_call', $array['call_type']);
        $this->assertSame('/app/file.php', $array['file']);
        $this->assertSame(42, $array['line']);
        $this->assertSame(0.8, $array['confidence']);
        $this->assertSame(['key' => 'value'], $array['context']);
    }

    public function testFromArray(): void
    {
        $data = [
            'caller_fqn' => 'A::b',
            'callee_fqn' => 'C::d',
            'call_type' => 'method_call',
            'file' => '/app/file.php',
            'line' => 42,
            'confidence' => 0.9,
            'context' => ['test' => true],
        ];

        $ref = CallReference::fromArray($data);

        $this->assertSame('A::b', $ref->callerFqn);
        $this->assertSame('C::d', $ref->calleeFqn);
        $this->assertSame(CallType::MethodCall, $ref->callType);
        $this->assertSame('/app/file.php', $ref->file);
        $this->assertSame(42, $ref->line);
        $this->assertSame(0.9, $ref->confidence);
        $this->assertSame(['test' => true], $ref->context);
    }

    public function testRoundTrip(): void
    {
        $original = new CallReference(
            callerFqn: 'App\\Test::method',
            calleeFqn: 'App\\Other::call',
            callType: CallType::NullsafeCall,
            file: '/test.php',
            line: 100,
            confidence: 0.75,
            context: ['nullable' => true],
        );

        $restored = CallReference::fromArray($original->toArray());

        $this->assertSame($original->callerFqn, $restored->callerFqn);
        $this->assertSame($original->calleeFqn, $restored->calleeFqn);
        $this->assertSame($original->callType, $restored->callType);
        $this->assertSame($original->file, $restored->file);
        $this->assertSame($original->line, $restored->line);
        $this->assertSame($original->confidence, $restored->confidence);
        $this->assertSame($original->context, $restored->context);
    }
}
