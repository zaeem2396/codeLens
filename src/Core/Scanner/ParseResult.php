<?php

declare(strict_types=1);

namespace CodeLens\Core\Scanner;

use CodeLens\Core\Index\Symbols\SymbolInterface;

/**
 * Represents the result of parsing a PHP file.
 */
final readonly class ParseResult
{
    /**
     * @param array<SymbolInterface> $symbols
     * @param array<string, string> $useStatements Alias => FQCN
     */
    private function __construct(
        public string $filePath,
        public bool $success,
        public ?string $error,
        public array $symbols,
        public ?string $namespace,
        public array $useStatements
    ) {}

    /**
     * Create a successful parse result.
     * 
     * @param array<SymbolInterface> $symbols
     * @param array<string, string> $useStatements
     */
    public static function success(
        string $filePath,
        array $symbols,
        ?string $namespace = null,
        array $useStatements = []
    ): self {
        return new self(
            filePath: $filePath,
            success: true,
            error: null,
            symbols: $symbols,
            namespace: $namespace,
            useStatements: $useStatements
        );
    }

    /**
     * Create an error parse result.
     */
    public static function error(string $filePath, string $error): self
    {
        return new self(
            filePath: $filePath,
            success: false,
            error: $error,
            symbols: [],
            namespace: null,
            useStatements: []
        );
    }

    /**
     * Check if parsing was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if parsing failed.
     */
    public function isError(): bool
    {
        return !$this->success;
    }

    /**
     * Get all class symbols.
     */
    public function getClasses(): array
    {
        return array_filter(
            $this->symbols,
            fn($s) => $s->getType() === 'class'
        );
    }

    /**
     * Get all interface symbols.
     */
    public function getInterfaces(): array
    {
        return array_filter(
            $this->symbols,
            fn($s) => $s->getType() === 'interface'
        );
    }

    /**
     * Get all trait symbols.
     */
    public function getTraits(): array
    {
        return array_filter(
            $this->symbols,
            fn($s) => $s->getType() === 'trait'
        );
    }

    /**
     * Get all enum symbols.
     */
    public function getEnums(): array
    {
        return array_filter(
            $this->symbols,
            fn($s) => $s->getType() === 'enum'
        );
    }

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'success' => $this->success,
            'error' => $this->error,
            'namespace' => $this->namespace,
            'use_statements' => $this->useStatements,
            'symbols' => array_map(fn($s) => $s->toArray(), $this->symbols),
        ];
    }
}

