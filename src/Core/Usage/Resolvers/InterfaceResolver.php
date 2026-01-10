<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage\Resolvers;

use CodeLens\Core\Index\SymbolRegistry;
use CodeLens\Core\Index\Symbols\ClassSymbol;
use CodeLens\Core\Index\Symbols\InterfaceSymbol;
use CodeLens\Core\Usage\CallReference;
use CodeLens\Core\Usage\CallType;

/**
 * Resolves interface method calls to their implementations.
 *
 * Maps interface type hints to concrete implementations
 * for more accurate call graph analysis.
 */
final class InterfaceResolver implements ResolverInterface
{
    /** @var array<string, array<string>> Cache: interface FQN -> implementing class FQNs */
    private array $implementationCache = [];

    public function getId(): string
    {
        return 'interface';
    }

    public function getName(): string
    {
        return 'Interface Resolver';
    }

    public function getPriority(): int
    {
        return 70; // Lower priority than direct calls
    }

    public function canResolve(CallReference $reference): bool
    {
        return in_array($reference->callType, [
            CallType::MethodCall,
            CallType::StaticCall,
            CallType::NullsafeCall,
        ], true);
    }

    public function resolve(CallReference $reference, SymbolRegistry $registry): CallReference
    {
        $calleeFqn = $reference->calleeFqn;

        // Only process method calls with class::method format
        if (! str_contains($calleeFqn, '::')) {
            return $reference;
        }

        [$classFqn, $methodName] = explode('::', $calleeFqn, 2);

        // Check if the class is an interface
        $symbol = $registry->get($classFqn);

        if ($symbol === null || ! $symbol instanceof InterfaceSymbol) {
            return $reference;
        }

        // Find implementations
        $implementations = $this->findImplementations($classFqn, $registry);

        if (empty($implementations)) {
            // No implementations found
            return new CallReference(
                callerFqn: $reference->callerFqn,
                calleeFqn: $calleeFqn,
                callType: CallType::Interface,
                file: $reference->file,
                line: $reference->line,
                confidence: 0.5, // Lower confidence - interface with no known implementations
                context: array_merge($reference->context, [
                    'is_interface' => true,
                    'implementations' => [],
                ]),
            );
        }

        // Build list of possible implementations
        $possibleTargets = array_map(
            fn (string $impl) => $impl . '::' . $methodName,
            $implementations,
        );

        return new CallReference(
            callerFqn: $reference->callerFqn,
            calleeFqn: $calleeFqn,
            callType: CallType::Interface,
            file: $reference->file,
            line: $reference->line,
            confidence: $this->calculateConfidence(count($implementations)),
            context: array_merge($reference->context, [
                'is_interface' => true,
                'implementations' => $implementations,
                'possible_targets' => $possibleTargets,
            ]),
        );
    }

    /**
     * Find all classes that implement an interface.
     *
     * @return array<string>
     */
    public function findImplementations(string $interfaceFqn, SymbolRegistry $registry): array
    {
        // Check cache
        if (isset($this->implementationCache[$interfaceFqn])) {
            return $this->implementationCache[$interfaceFqn];
        }

        $implementations = [];

        // Search through all classes
        foreach ($registry->getClasses() as $classSymbol) {
            if (! $classSymbol instanceof ClassSymbol) {
                continue;
            }

            if (in_array($interfaceFqn, $classSymbol->implements, true)) {
                $implementations[] = $classSymbol->fqn;
            }
        }

        // Cache result
        $this->implementationCache[$interfaceFqn] = $implementations;

        return $implementations;
    }

    /**
     * Calculate confidence based on implementation count.
     *
     * More implementations = less certainty about which one is called.
     */
    private function calculateConfidence(int $implementationCount): float
    {
        if ($implementationCount === 0) {
            return 0.5;
        }

        if ($implementationCount === 1) {
            return 0.9; // Single implementation - high confidence
        }

        if ($implementationCount <= 3) {
            return 0.7; // Few implementations
        }

        if ($implementationCount <= 10) {
            return 0.6; // Several implementations
        }

        return 0.5; // Many implementations - hard to know which
    }

    /**
     * Clear the implementation cache.
     */
    public function clearCache(): void
    {
        $this->implementationCache = [];
    }

    /**
     * Build implementation map for all interfaces.
     *
     * @return array<string, array<string>>
     */
    public function buildImplementationMap(SymbolRegistry $registry): array
    {
        $map = [];

        // Get all interfaces
        foreach ($registry->getInterfaces() as $interface) {
            if ($interface instanceof InterfaceSymbol) {
                $implementations = $this->findImplementations($interface->fqn, $registry);
                if (! empty($implementations)) {
                    $map[$interface->fqn] = $implementations;
                }
            }
        }

        return $map;
    }
}
