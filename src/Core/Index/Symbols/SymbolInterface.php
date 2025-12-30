<?php

declare(strict_types=1);

namespace CodeLens\Core\Index\Symbols;

/**
 * Interface for all symbol types.
 */
interface SymbolInterface
{
    /**
     * Get the symbol name.
     */
    public function getName(): string;

    /**
     * Get the fully qualified name.
     */
    public function getFqn(): string;

    /**
     * Get the symbol type (class, interface, trait, enum, method, property).
     */
    public function getType(): string;

    /**
     * Get the file path where this symbol is defined.
     */
    public function getFile(): string;

    /**
     * Get the starting line number.
     */
    public function getLineStart(): int;

    /**
     * Get the ending line number.
     */
    public function getLineEnd(): int;

    /**
     * Convert to array for storage.
     */
    public function toArray(): array;
}

