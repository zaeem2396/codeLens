<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core\Usage;

use CodeLens\Core\Usage\CallType;
use PHPUnit\Framework\TestCase;

class CallTypeTest extends TestCase
{
    public function testAllCallTypesHaveValues(): void
    {
        $this->assertSame('method_call', CallType::MethodCall->value);
        $this->assertSame('static_call', CallType::StaticCall->value);
        $this->assertSame('new_instance', CallType::NewInstance->value);
        $this->assertSame('function_call', CallType::FunctionCall->value);
        $this->assertSame('closure', CallType::Closure->value);
        $this->assertSame('interface', CallType::Interface->value);
        $this->assertSame('nullsafe_call', CallType::NullsafeCall->value);
    }

    public function testLabel(): void
    {
        $this->assertSame('Method Call', CallType::MethodCall->label());
        $this->assertSame('Static Call', CallType::StaticCall->label());
        $this->assertSame('New Instance', CallType::NewInstance->label());
        $this->assertSame('Function Call', CallType::FunctionCall->label());
        $this->assertSame('Closure', CallType::Closure->label());
        $this->assertSame('Interface', CallType::Interface->label());
        $this->assertSame('Nullsafe Call', CallType::NullsafeCall->label());
    }

    public function testDescription(): void
    {
        $this->assertStringContainsString('$obj->method()', CallType::MethodCall->description());
        $this->assertStringContainsString('Class::method()', CallType::StaticCall->description());
        $this->assertStringContainsString('new Class()', CallType::NewInstance->description());
    }

    public function testFromValue(): void
    {
        $this->assertSame(CallType::MethodCall, CallType::from('method_call'));
        $this->assertSame(CallType::StaticCall, CallType::from('static_call'));
        $this->assertSame(CallType::NewInstance, CallType::from('new_instance'));
    }
}
