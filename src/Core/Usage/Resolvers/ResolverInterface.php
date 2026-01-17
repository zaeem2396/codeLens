<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage\Resolvers;

use CodeLens\Core\Index\SymbolRegistry;
use CodeLens\Core\Usage\CallReference;

/**
 * Interface for call resolvers.
 *
 * Resolvers attempt to resolve call references to their actual targets,
 * improving the accuracy of the call graph.
 */
interface ResolverInterface
{
    /**
     * Get the resolver identifier.
     */
    public function getId(): string;

    /**
     * Get a human-readable name.
     */
    public function getName(): string;

    /**
     * Check if this resolver can handle the given reference.
     */
    public function canResolve(CallReference $reference): bool;

    /**
     * Attempt to resolve a call reference.
     *
     * Returns a new CallReference with improved callee FQN and confidence,
     * or the original reference if no improvement could be made.
     */
    public function resolve(CallReference $reference, SymbolRegistry $registry): CallReference;

    /**
     * Get the priority of this resolver (higher = runs first).
     */
    public function getPriority(): int;
}
