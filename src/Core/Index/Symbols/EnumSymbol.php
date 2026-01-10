<?php

declare(strict_types=1);

namespace CodeLens\Core\Index\Symbols;

/**
 * Represents an enum in the codebase.
 */
final class EnumSymbol implements SymbolInterface
{
    /**
     * @param array<string> $implements
     * @param array<string> $cases
     * @param array<MethodSymbol> $methods
     */
    public function __construct(
        public readonly string $name,
        public readonly string $fqn,
        public readonly string $file,
        public readonly int $lineStart,
        public readonly int $lineEnd,
        public readonly ?string $namespace = null,
        public readonly array $implements = [],
        public readonly array $cases = [],
        public readonly array $methods = [],
        public readonly ?string $scalarType = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFqn(): string
    {
        return $this->fqn;
    }

    public function getType(): string
    {
        return 'enum';
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLineStart(): int
    {
        return $this->lineStart;
    }

    public function getLineEnd(): int
    {
        return $this->lineEnd;
    }

    /**
     * Get case count.
     */
    public function getCaseCount(): int
    {
        return count($this->cases);
    }

    /**
     * Get method count.
     */
    public function getMethodCount(): int
    {
        return count($this->methods);
    }

    /**
     * Check if enum is backed (has scalar type).
     */
    public function isBacked(): bool
    {
        return $this->scalarType !== null;
    }

    public function toArray(): array
    {
        return [
            'type' => 'enum',
            'name' => $this->name,
            'fqn' => $this->fqn,
            'file' => $this->file,
            'line_start' => $this->lineStart,
            'line_end' => $this->lineEnd,
            'namespace' => $this->namespace,
            'implements' => $this->implements,
            'cases' => $this->cases,
            'methods' => array_map(fn ($m) => $m->toArray(), $this->methods),
            'scalar_type' => $this->scalarType,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            fqn: $data['fqn'],
            file: $data['file'],
            lineStart: $data['line_start'],
            lineEnd: $data['line_end'],
            namespace: $data['namespace'] ?? null,
            implements: $data['implements'] ?? [],
            cases: $data['cases'] ?? [],
            methods: array_map(fn ($m) => MethodSymbol::fromArray($m), $data['methods'] ?? []),
            scalarType: $data['scalar_type'] ?? null,
        );
    }
}
