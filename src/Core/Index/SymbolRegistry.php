<?php

declare(strict_types=1);

namespace CodeLens\Core\Index;

use CodeLens\Core\Index\Symbols\ClassSymbol;
use CodeLens\Core\Index\Symbols\EnumSymbol;
use CodeLens\Core\Index\Symbols\InterfaceSymbol;
use CodeLens\Core\Index\Symbols\SymbolInterface;
use CodeLens\Core\Index\Symbols\TraitSymbol;

/**
 * Registry of all symbols in the codebase.
 * 
 * Stores and indexes classes, interfaces, traits, and enums
 * for quick lookup by FQN or namespace.
 */
final class SymbolRegistry
{
    /** @var array<string, SymbolInterface> FQN => Symbol */
    private array $symbols = [];

    /** @var array<string, array<string>> Namespace => FQNs */
    private array $namespaceIndex = [];

    /** @var array<string, array<string>> File => FQNs */
    private array $fileIndex = [];

    /** @var array<string, array<string>> Type => FQNs */
    private array $typeIndex = [];

    /**
     * Register a symbol.
     */
    public function register(SymbolInterface $symbol): void
    {
        $fqn = $symbol->getFqn();
        $this->symbols[$fqn] = $symbol;

        // Index by file
        $file = $symbol->getFile();
        if (!isset($this->fileIndex[$file])) {
            $this->fileIndex[$file] = [];
        }
        $this->fileIndex[$file][] = $fqn;

        // Index by type
        $type = $symbol->getType();
        if (!isset($this->typeIndex[$type])) {
            $this->typeIndex[$type] = [];
        }
        $this->typeIndex[$type][] = $fqn;

        // Index by namespace
        $namespace = $this->extractNamespace($symbol);
        if ($namespace !== null) {
            if (!isset($this->namespaceIndex[$namespace])) {
                $this->namespaceIndex[$namespace] = [];
            }
            $this->namespaceIndex[$namespace][] = $fqn;
        }
    }

    /**
     * Get a symbol by FQN.
     */
    public function get(string $fqn): ?SymbolInterface
    {
        return $this->symbols[$fqn] ?? null;
    }

    /**
     * Check if a symbol exists.
     */
    public function has(string $fqn): bool
    {
        return isset($this->symbols[$fqn]);
    }

    /**
     * Remove a symbol.
     */
    public function remove(string $fqn): void
    {
        if (!isset($this->symbols[$fqn])) {
            return;
        }

        $symbol = $this->symbols[$fqn];
        unset($this->symbols[$fqn]);

        // Remove from file index
        $file = $symbol->getFile();
        if (isset($this->fileIndex[$file])) {
            $this->fileIndex[$file] = array_filter(
                $this->fileIndex[$file],
                fn($f) => $f !== $fqn
            );
        }

        // Remove from type index
        $type = $symbol->getType();
        if (isset($this->typeIndex[$type])) {
            $this->typeIndex[$type] = array_filter(
                $this->typeIndex[$type],
                fn($f) => $f !== $fqn
            );
        }

        // Remove from namespace index
        $namespace = $this->extractNamespace($symbol);
        if ($namespace !== null && isset($this->namespaceIndex[$namespace])) {
            $this->namespaceIndex[$namespace] = array_filter(
                $this->namespaceIndex[$namespace],
                fn($f) => $f !== $fqn
            );
        }
    }

    /**
     * Remove all symbols from a file.
     */
    public function removeByFile(string $file): void
    {
        if (!isset($this->fileIndex[$file])) {
            return;
        }

        foreach ($this->fileIndex[$file] as $fqn) {
            $this->remove($fqn);
        }
    }

    /**
     * Get all symbols.
     * 
     * @return array<string, SymbolInterface>
     */
    public function all(): array
    {
        return $this->symbols;
    }

    /**
     * Get all class symbols.
     * 
     * @return array<ClassSymbol>
     */
    public function getClasses(): array
    {
        return $this->getByType('class');
    }

    /**
     * Get all interface symbols.
     * 
     * @return array<InterfaceSymbol>
     */
    public function getInterfaces(): array
    {
        return $this->getByType('interface');
    }

    /**
     * Get all trait symbols.
     * 
     * @return array<TraitSymbol>
     */
    public function getTraits(): array
    {
        return $this->getByType('trait');
    }

    /**
     * Get all enum symbols.
     * 
     * @return array<EnumSymbol>
     */
    public function getEnums(): array
    {
        return $this->getByType('enum');
    }

    /**
     * Get symbols by type.
     * 
     * @return array<SymbolInterface>
     */
    public function getByType(string $type): array
    {
        if (!isset($this->typeIndex[$type])) {
            return [];
        }

        return array_filter(
            array_map(fn($fqn) => $this->symbols[$fqn] ?? null, $this->typeIndex[$type]),
            fn($s) => $s !== null
        );
    }

    /**
     * Get symbols by file.
     * 
     * @return array<SymbolInterface>
     */
    public function getByFile(string $file): array
    {
        if (!isset($this->fileIndex[$file])) {
            return [];
        }

        return array_filter(
            array_map(fn($fqn) => $this->symbols[$fqn] ?? null, $this->fileIndex[$file]),
            fn($s) => $s !== null
        );
    }

    /**
     * Get symbols by namespace.
     * 
     * @return array<SymbolInterface>
     */
    public function getByNamespace(string $namespace): array
    {
        if (!isset($this->namespaceIndex[$namespace])) {
            return [];
        }

        return array_filter(
            array_map(fn($fqn) => $this->symbols[$fqn] ?? null, $this->namespaceIndex[$namespace]),
            fn($s) => $s !== null
        );
    }

    /**
     * Get all namespaces.
     * 
     * @return array<string>
     */
    public function getNamespaces(): array
    {
        return array_keys($this->namespaceIndex);
    }

    /**
     * Get total symbol count.
     */
    public function count(): int
    {
        return count($this->symbols);
    }

    /**
     * Get count by type.
     */
    public function countByType(string $type): int
    {
        return count($this->typeIndex[$type] ?? []);
    }

    /**
     * Clear all symbols.
     */
    public function clear(): void
    {
        $this->symbols = [];
        $this->namespaceIndex = [];
        $this->fileIndex = [];
        $this->typeIndex = [];
    }

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->symbols as $fqn => $symbol) {
            $data[$fqn] = $symbol->toArray();
        }
        return $data;
    }

    /**
     * Extract namespace from symbol.
     */
    private function extractNamespace(SymbolInterface $symbol): ?string
    {
        // Use reflection-like access for namespace property
        if ($symbol instanceof ClassSymbol ||
            $symbol instanceof InterfaceSymbol ||
            $symbol instanceof TraitSymbol ||
            $symbol instanceof EnumSymbol) {
            return $symbol->namespace;
        }

        return null;
    }
}

