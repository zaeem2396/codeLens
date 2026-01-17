<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage;

/**
 * Types of call relationships that can be detected.
 */
enum CallType: string
{
    case MethodCall = 'method_call';
    case StaticCall = 'static_call';
    case NewInstance = 'new_instance';
    case FunctionCall = 'function_call';
    case Closure = 'closure';
    case Interface = 'interface';
    case NullsafeCall = 'nullsafe_call';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::MethodCall => 'Method Call',
            self::StaticCall => 'Static Call',
            self::NewInstance => 'New Instance',
            self::FunctionCall => 'Function Call',
            self::Closure => 'Closure',
            self::Interface => 'Interface',
            self::NullsafeCall => 'Nullsafe Call',
        };
    }

    /**
     * Get description of call type.
     */
    public function description(): string
    {
        return match ($this) {
            self::MethodCall => 'Instance method call ($obj->method())',
            self::StaticCall => 'Static method call (Class::method())',
            self::NewInstance => 'Object instantiation (new Class())',
            self::FunctionCall => 'Standalone function call',
            self::Closure => 'Closure or arrow function',
            self::Interface => 'Interface method implementation',
            self::NullsafeCall => 'Nullsafe method call ($obj?->method())',
        };
    }
}
