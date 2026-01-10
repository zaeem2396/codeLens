<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage\Resolvers;

use CodeLens\Core\Index\SymbolRegistry;
use CodeLens\Core\Usage\CallReference;
use CodeLens\Core\Usage\CallType;

/**
 * Resolves closure and arrow function references.
 *
 * Handles:
 * - Closures with use() variables
 * - Arrow functions with captured variables
 * - Callback patterns (array_map, array_filter, etc.)
 */
final class ClosureResolver implements ResolverInterface
{
    public function getId(): string
    {
        return 'closure';
    }

    public function getName(): string
    {
        return 'Closure Resolver';
    }

    public function getPriority(): int
    {
        return 60; // Lower priority
    }

    public function canResolve(CallReference $reference): bool
    {
        return $reference->callType === CallType::Closure;
    }

    public function resolve(CallReference $reference, SymbolRegistry $registry): CallReference
    {
        $context = $reference->context;

        // Extract used variables if available
        $usedVars = $context['uses'] ?? [];
        $closureType = $context['type'] ?? 'closure';

        // Calculate confidence based on what we know
        $confidence = $this->calculateConfidence($usedVars, $closureType);

        // Enhance context with analysis
        $enhancedContext = array_merge($context, [
            'closure_type' => $closureType,
            'captured_variables' => count($usedVars),
            'analysis' => $this->analyzeClosurePattern($usedVars),
        ]);

        return new CallReference(
            callerFqn: $reference->callerFqn,
            calleeFqn: $reference->calleeFqn,
            callType: $reference->callType,
            file: $reference->file,
            line: $reference->line,
            confidence: $confidence,
            context: $enhancedContext,
        );
    }

    /**
     * Calculate confidence based on closure characteristics.
     */
    private function calculateConfidence(array $usedVars, string $closureType): float
    {
        $baseConfidence = $closureType === 'arrow_function' ? 0.6 : 0.7;

        // More captured variables = more complex = less certainty
        $varCount = count($usedVars);

        if ($varCount === 0) {
            return $baseConfidence + 0.1; // Pure closure
        }

        if ($varCount <= 2) {
            return $baseConfidence;
        }

        if ($varCount <= 5) {
            return $baseConfidence - 0.1;
        }

        return $baseConfidence - 0.2;
    }

    /**
     * Analyze the closure pattern for additional insights.
     */
    private function analyzeClosurePattern(array $usedVars): array
    {
        $analysis = [
            'is_pure' => empty($usedVars),
            'captures_this' => in_array('$this', $usedVars, true),
            'captures_service' => $this->detectsServicePattern($usedVars),
        ];

        return $analysis;
    }

    /**
     * Detect if the closure captures service/repository patterns.
     */
    private function detectsServicePattern(array $usedVars): bool
    {
        $servicePatterns = [
            'service', 'repository', 'handler', 'manager',
            'factory', 'provider', 'resolver', 'dispatcher',
        ];

        foreach ($usedVars as $var) {
            $varLower = strtolower($var);
            foreach ($servicePatterns as $pattern) {
                if (str_contains($varLower, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect common callback patterns.
     *
     * @return array<string, mixed>
     */
    public function detectCallbackPattern(CallReference $reference): array
    {
        $callerMethod = $reference->getCallerMethod();

        // Common PHP callback functions
        $callbackFunctions = [
            'array_map' => ['position' => 0, 'type' => 'transform'],
            'array_filter' => ['position' => 1, 'type' => 'filter'],
            'array_reduce' => ['position' => 1, 'type' => 'reduce'],
            'array_walk' => ['position' => 1, 'type' => 'iterate'],
            'usort' => ['position' => 1, 'type' => 'compare'],
            'uasort' => ['position' => 1, 'type' => 'compare'],
            'uksort' => ['position' => 1, 'type' => 'compare'],
            'call_user_func' => ['position' => 0, 'type' => 'invoke'],
            'call_user_func_array' => ['position' => 0, 'type' => 'invoke'],
        ];

        $context = $reference->context;

        // Check if we're in a callback context
        if (isset($context['callback_function'])) {
            $funcName = $context['callback_function'];
            if (isset($callbackFunctions[$funcName])) {
                return [
                    'is_callback' => true,
                    'function' => $funcName,
                    'pattern' => $callbackFunctions[$funcName],
                ];
            }
        }

        return [
            'is_callback' => false,
        ];
    }
}
