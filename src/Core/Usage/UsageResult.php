<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage;

/**
 * Result container for usage analysis.
 *
 * Contains the call graph and summary statistics.
 */
final class UsageResult
{
    public function __construct(
        public readonly CallGraph $callGraph,
        public readonly float $duration,
        public readonly int $filesAnalyzed = 0,
        public readonly array $errors = [],
    ) {
    }

    /**
     * Get the call graph.
     */
    public function getCallGraph(): CallGraph
    {
        return $this->callGraph;
    }

    /**
     * Get total reference count.
     */
    public function getTotalReferences(): int
    {
        return $this->callGraph->count();
    }

    /**
     * Get count of unique callers.
     */
    public function getUniqueCallers(): int
    {
        return count($this->callGraph->getCallers());
    }

    /**
     * Get count of unique callees.
     */
    public function getUniqueCallees(): int
    {
        return count($this->callGraph->getCallees());
    }

    /**
     * Get count of entry points.
     */
    public function getEntryPointCount(): int
    {
        return count($this->callGraph->getEntryPoints());
    }

    /**
     * Check if there were errors.
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Get error count.
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDuration(): string
    {
        if ($this->duration < 1) {
            return round($this->duration * 1000) . 'ms';
        }

        return round($this->duration, 2) . 's';
    }

    /**
     * Get summary of the analysis.
     */
    public function getSummary(): array
    {
        $graphStats = $this->callGraph->getStats();

        return [
            'files_analyzed' => $this->filesAnalyzed,
            'total_references' => $graphStats['total_references'],
            'unique_callers' => $graphStats['unique_callers'],
            'unique_callees' => $graphStats['unique_callees'],
            'entry_points' => $graphStats['entry_points'],
            'by_type' => $graphStats['by_type'],
            'duration' => $this->getFormattedDuration(),
            'error_count' => $this->getErrorCount(),
        ];
    }

    /**
     * Get incoming calls for a FQN.
     *
     * @return array<CallReference>
     */
    public function getCallersOf(string $fqn): array
    {
        return $this->callGraph->getIncomingCalls($fqn);
    }

    /**
     * Get outgoing calls from a FQN.
     *
     * @return array<CallReference>
     */
    public function getCallsFrom(string $fqn): array
    {
        return $this->callGraph->getOutgoingCalls($fqn);
    }

    /**
     * Get all calls to/from a class.
     */
    public function getClassUsage(string $classFqn): array
    {
        return [
            'incoming' => $this->callGraph->getIncomingCallsForClass($classFqn),
            'outgoing' => $this->callGraph->getOutgoingCallsForClass($classFqn),
        ];
    }

    /**
     * Convert to array for storage/display.
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->getSummary(),
            'call_graph' => $this->callGraph->toArray(),
            'errors' => $this->errors,
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            callGraph: CallGraph::fromArray($data['call_graph'] ?? []),
            duration: 0,
            filesAnalyzed: $data['summary']['files_analyzed'] ?? 0,
            errors: $data['errors'] ?? [],
        );
    }
}
