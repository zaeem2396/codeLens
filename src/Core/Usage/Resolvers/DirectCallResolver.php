<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage\Resolvers;

use CodeLens\Core\Index\SymbolRegistry;
use CodeLens\Core\Index\Symbols\ClassSymbol;
use CodeLens\Core\Usage\CallReference;
use CodeLens\Core\Usage\CallType;

/**
 * Resolves direct method calls where the target is known.
 *
 * Handles:
 * - $this->method() - resolves to current class
 * - self::method() - resolves to current class
 * - static::method() - resolves to current class
 * - ClassName::method() - resolves fully qualified names
 */
final class DirectCallResolver implements ResolverInterface
{
    public function getId(): string
    {
        return 'direct_call';
    }

    public function getName(): string
    {
        return 'Direct Call Resolver';
    }

    public function getPriority(): int
    {
        return 100; // High priority - runs first
    }

    public function canResolve(CallReference $reference): bool
    {
        // Can resolve method calls, static calls, and new instances
        return in_array($reference->callType, [
            CallType::MethodCall,
            CallType::StaticCall,
            CallType::NewInstance,
            CallType::NullsafeCall,
        ], true);
    }

    public function resolve(CallReference $reference, SymbolRegistry $registry): CallReference
    {
        $calleeFqn = $reference->calleeFqn;
        $confidence = $reference->confidence;

        // Already resolved with high confidence
        if ($confidence >= 1.0 && ! str_contains($calleeFqn, '(unresolved)')) {
            return $reference;
        }

        // Handle unresolved calls
        if (str_starts_with($calleeFqn, '(unresolved)::')) {
            // Can't resolve without type information
            return new CallReference(
                callerFqn: $reference->callerFqn,
                calleeFqn: $calleeFqn,
                callType: $reference->callType,
                file: $reference->file,
                line: $reference->line,
                confidence: 0.3, // Low confidence for unresolved
                context: $reference->context,
            );
        }

        // For new instances, check if class exists in registry
        if ($reference->callType === CallType::NewInstance) {
            if ($registry->has($calleeFqn)) {
                return new CallReference(
                    callerFqn: $reference->callerFqn,
                    calleeFqn: $calleeFqn,
                    callType: $reference->callType,
                    file: $reference->file,
                    line: $reference->line,
                    confidence: 1.0,
                    context: $reference->context,
                );
            }

            // Class not in registry, might be external
            return new CallReference(
                callerFqn: $reference->callerFqn,
                calleeFqn: $calleeFqn,
                callType: $reference->callType,
                file: $reference->file,
                line: $reference->line,
                confidence: 0.8, // Slightly lower for external classes
                context: array_merge($reference->context, ['external' => true]),
            );
        }

        // For method calls, verify the class::method exists
        if (str_contains($calleeFqn, '::')) {
            [$classFqn, $methodName] = explode('::', $calleeFqn, 2);

            // Handle parent:: calls
            if (str_ends_with($classFqn, '::parent')) {
                $actualClass = str_replace('::parent', '', $classFqn);
                $classSymbol = $registry->get($actualClass);

                if ($classSymbol instanceof ClassSymbol && $classSymbol->extends !== null) {
                    $parentFqn = $classSymbol->extends . '::' . $methodName;

                    return new CallReference(
                        callerFqn: $reference->callerFqn,
                        calleeFqn: $parentFqn,
                        callType: $reference->callType,
                        file: $reference->file,
                        line: $reference->line,
                        confidence: 0.9,
                        context: array_merge($reference->context, ['resolved_from' => 'parent']),
                    );
                }
            }

            // Check if class exists
            $classSymbol = $registry->get($classFqn);

            if ($classSymbol !== null) {
                // Class exists - check for method
                $hasMethod = $this->classHasMethod($classSymbol, $methodName);

                return new CallReference(
                    callerFqn: $reference->callerFqn,
                    calleeFqn: $calleeFqn,
                    callType: $reference->callType,
                    file: $reference->file,
                    line: $reference->line,
                    confidence: $hasMethod ? 1.0 : 0.7,
                    context: array_merge($reference->context, ['method_exists' => $hasMethod]),
                );
            }

            // Class not found - might be external
            return new CallReference(
                callerFqn: $reference->callerFqn,
                calleeFqn: $calleeFqn,
                callType: $reference->callType,
                file: $reference->file,
                line: $reference->line,
                confidence: 0.7,
                context: array_merge($reference->context, ['external' => true]),
            );
        }

        return $reference;
    }

    /**
     * Check if a class symbol has a specific method.
     */
    private function classHasMethod(object $classSymbol, string $methodName): bool
    {
        if (! property_exists($classSymbol, 'methods')) {
            return false;
        }

        foreach ($classSymbol->methods as $method) {
            if (property_exists($method, 'name') && $method->name === $methodName) {
                return true;
            }
        }

        return false;
    }
}

