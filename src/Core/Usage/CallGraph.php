<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage;

/**
 * Graph data structure for storing call relationships.
 *
 * Provides O(1) lookup for both incoming and outgoing edges.
 */
final class CallGraph
{
    /** @var array<string, array<CallReference>> Outgoing edges: caller -> [references] */
    private array $outgoing = [];

    /** @var array<string, array<CallReference>> Incoming edges: callee -> [references] */
    private array $incoming = [];

    /** @var array<CallReference> All references */
    private array $allReferences = [];

    /**
     * Add a call reference to the graph.
     */
    public function addReference(CallReference $reference): void
    {
        $this->allReferences[] = $reference;

        // Add to outgoing edges
        if (! isset($this->outgoing[$reference->callerFqn])) {
            $this->outgoing[$reference->callerFqn] = [];
        }
        $this->outgoing[$reference->callerFqn][] = $reference;

        // Add to incoming edges
        if (! isset($this->incoming[$reference->calleeFqn])) {
            $this->incoming[$reference->calleeFqn] = [];
        }
        $this->incoming[$reference->calleeFqn][] = $reference;
    }

    /**
     * Add multiple references.
     *
     * @param array<CallReference> $references
     */
    public function addReferences(array $references): void
    {
        foreach ($references as $reference) {
            $this->addReference($reference);
        }
    }

    /**
     * Get all outgoing calls from a method/class.
     *
     * "What does this method call?"
     *
     * @return array<CallReference>
     */
    public function getOutgoingCalls(string $fqn): array
    {
        return $this->outgoing[$fqn] ?? [];
    }

    /**
     * Get all incoming calls to a method/class.
     *
     * "Who calls this method?"
     *
     * @return array<CallReference>
     */
    public function getIncomingCalls(string $fqn): array
    {
        return $this->incoming[$fqn] ?? [];
    }

    /**
     * Get all outgoing calls from a class (all its methods).
     *
     * @return array<CallReference>
     */
    public function getOutgoingCallsForClass(string $classFqn): array
    {
        $result = [];

        foreach ($this->outgoing as $callerFqn => $references) {
            if (str_starts_with($callerFqn, $classFqn . '::')) {
                $result = array_merge($result, $references);
            }
        }

        return $result;
    }

    /**
     * Get all incoming calls to a class (all its methods).
     *
     * @return array<CallReference>
     */
    public function getIncomingCallsForClass(string $classFqn): array
    {
        $result = [];

        // Calls to class methods
        foreach ($this->incoming as $calleeFqn => $references) {
            if (str_starts_with($calleeFqn, $classFqn . '::')) {
                $result = array_merge($result, $references);
            }
        }

        // Direct instantiations
        if (isset($this->incoming[$classFqn])) {
            $result = array_merge($result, $this->incoming[$classFqn]);
        }

        return $result;
    }

    /**
     * Get count of incoming calls for a method/class.
     */
    public function getIncomingCount(string $fqn): int
    {
        return count($this->incoming[$fqn] ?? []);
    }

    /**
     * Get count of outgoing calls from a method/class.
     */
    public function getOutgoingCount(string $fqn): int
    {
        return count($this->outgoing[$fqn] ?? []);
    }

    /**
     * Get all unique callers.
     *
     * @return array<string>
     */
    public function getCallers(): array
    {
        return array_keys($this->outgoing);
    }

    /**
     * Get all unique callees.
     *
     * @return array<string>
     */
    public function getCallees(): array
    {
        return array_keys($this->incoming);
    }

    /**
     * Check if a method/class has any incoming calls.
     */
    public function hasIncomingCalls(string $fqn): bool
    {
        return isset($this->incoming[$fqn]) && count($this->incoming[$fqn]) > 0;
    }

    /**
     * Check if a method/class makes any outgoing calls.
     */
    public function hasOutgoingCalls(string $fqn): bool
    {
        return isset($this->outgoing[$fqn]) && count($this->outgoing[$fqn]) > 0;
    }

    /**
     * Get potential entry points (methods with no incoming calls).
     *
     * @return array<string>
     */
    public function getEntryPoints(): array
    {
        $allCallers = array_keys($this->outgoing);
        $allCallees = array_keys($this->incoming);

        // Entry points are callers that are never called themselves
        return array_diff($allCallers, $allCallees);
    }

    /**
     * Get all references.
     *
     * @return array<CallReference>
     */
    public function getAllReferences(): array
    {
        return $this->allReferences;
    }

    /**
     * Get total reference count.
     */
    public function count(): int
    {
        return count($this->allReferences);
    }

    /**
     * Get references by call type.
     *
     * @return array<CallReference>
     */
    public function getByCallType(CallType $type): array
    {
        return array_filter(
            $this->allReferences,
            fn (CallReference $ref) => $ref->callType === $type,
        );
    }

    /**
     * Get references for a specific file.
     *
     * @return array<CallReference>
     */
    public function getByFile(string $file): array
    {
        return array_filter(
            $this->allReferences,
            fn (CallReference $ref) => $ref->file === $file,
        );
    }

    /**
     * Get high-confidence references only.
     *
     * @return array<CallReference>
     */
    public function getHighConfidenceReferences(): array
    {
        return array_filter(
            $this->allReferences,
            fn (CallReference $ref) => $ref->isHighConfidence(),
        );
    }

    /**
     * Clear all references.
     */
    public function clear(): void
    {
        $this->outgoing = [];
        $this->incoming = [];
        $this->allReferences = [];
    }

    /**
     * Merge another call graph into this one.
     */
    public function merge(CallGraph $other): void
    {
        foreach ($other->getAllReferences() as $reference) {
            $this->addReference($reference);
        }
    }

    /**
     * Get statistics about the graph.
     */
    public function getStats(): array
    {
        $byType = [];
        foreach (CallType::cases() as $type) {
            $byType[$type->value] = count($this->getByCallType($type));
        }

        return [
            'total_references' => $this->count(),
            'unique_callers' => count($this->outgoing),
            'unique_callees' => count($this->incoming),
            'entry_points' => count($this->getEntryPoints()),
            'by_type' => $byType,
        ];
    }

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        return array_map(
            fn (CallReference $ref) => $ref->toArray(),
            $this->allReferences,
        );
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        $graph = new self();

        foreach ($data as $refData) {
            $graph->addReference(CallReference::fromArray($refData));
        }

        return $graph;
    }
}
