<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage\Resolvers;

use CodeLens\Core\Index\SymbolRegistry;
use CodeLens\Core\Index\Symbols\ClassSymbol;
use CodeLens\Core\Usage\CallReference;
use CodeLens\Core\Usage\CallType;

/**
 * Resolves constructor calls and dependency injection.
 *
 * Handles:
 * - new ClassName() - resolves constructor dependencies
 * - Constructor parameter types - identifies injected dependencies
 */
final class ConstructorResolver implements ResolverInterface
{
    public function getId(): string
    {
        return 'constructor';
    }

    public function getName(): string
    {
        return 'Constructor Resolver';
    }

    public function getPriority(): int
    {
        return 90; // High priority
    }

    public function canResolve(CallReference $reference): bool
    {
        return $reference->callType === CallType::NewInstance;
    }

    public function resolve(CallReference $reference, SymbolRegistry $registry): CallReference
    {
        $classFqn = $reference->calleeFqn;

        // Get the class symbol
        $classSymbol = $registry->get($classFqn);

        if ($classSymbol === null || ! $classSymbol instanceof ClassSymbol) {
            // Class not found or not a class symbol
            return $reference;
        }

        // Find constructor and its dependencies
        $dependencies = $this->extractConstructorDependencies($classSymbol);

        // Add dependency information to context
        $context = array_merge($reference->context, [
            'dependencies' => $dependencies,
            'dependency_count' => count($dependencies),
        ]);

        return new CallReference(
            callerFqn: $reference->callerFqn,
            calleeFqn: $classFqn,
            callType: $reference->callType,
            file: $reference->file,
            line: $reference->line,
            confidence: 1.0,
            context: $context,
        );
    }

    /**
     * Extract constructor dependencies from a class.
     *
     * @return array<array{name: string, type: string|null}>
     */
    private function extractConstructorDependencies(ClassSymbol $classSymbol): array
    {
        $dependencies = [];

        foreach ($classSymbol->methods as $method) {
            if ($method->name !== '__construct') {
                continue;
            }

            foreach ($method->parameters as $param) {
                $type = $param['type'] ?? null;

                // Only track class/interface type dependencies
                if ($type !== null && $this->isClassType($type)) {
                    $dependencies[] = [
                        'name' => $param['name'],
                        'type' => $type,
                    ];
                }
            }

            break; // Only one constructor
        }

        return $dependencies;
    }

    /**
     * Check if a type is a class/interface type (not a primitive).
     */
    private function isClassType(string $type): bool
    {
        // Handle nullable types
        if (str_starts_with($type, '?')) {
            $type = substr($type, 1);
        }

        // Handle union types - check first part
        if (str_contains($type, '|')) {
            $type = explode('|', $type)[0];
        }

        // Primitive types are not class types
        $primitives = [
            'int', 'integer', 'float', 'double', 'string', 'bool', 'boolean',
            'array', 'object', 'callable', 'iterable', 'mixed', 'void', 'null',
            'true', 'false', 'never', 'self', 'static', 'parent',
        ];

        return ! in_array(strtolower($type), $primitives, true);
    }

    /**
     * Get all dependencies for a class (constructor + property injection).
     *
     * @return array<string>
     */
    public function getAllDependencies(ClassSymbol $classSymbol, SymbolRegistry $registry): array
    {
        $dependencies = [];

        // Constructor dependencies
        foreach ($this->extractConstructorDependencies($classSymbol) as $dep) {
            if ($dep['type'] !== null) {
                $dependencies[] = $dep['type'];
            }
        }

        // Property type dependencies
        foreach ($classSymbol->properties as $property) {
            if (property_exists($property, 'type') && $property->type !== null) {
                $type = $property->type;

                // Handle nullable
                if (str_starts_with($type, '?')) {
                    $type = substr($type, 1);
                }

                if ($this->isClassType($type)) {
                    $dependencies[] = $type;
                }
            }
        }

        return array_unique($dependencies);
    }
}

