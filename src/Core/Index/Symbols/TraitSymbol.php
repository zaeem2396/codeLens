<?php

declare(strict_types=1);

namespace CodeLens\Core\Index\Symbols;

/**
 * Represents a trait in the codebase.
 */
final readonly class TraitSymbol implements SymbolInterface
{
    /**
     * @param array<MethodSymbol> $methods
     * @param array<PropertySymbol> $properties
     */
    public function __construct(
        public string $name,
        public string $fqn,
        public string $file,
        public int $lineStart,
        public int $lineEnd,
        public ?string $namespace = null,
        public array $methods = [],
        public array $properties = [],
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
        return 'trait';
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
     * Get method count.
     */
    public function getMethodCount(): int
    {
        return count($this->methods);
    }

    /**
     * Get property count.
     */
    public function getPropertyCount(): int
    {
        return count($this->properties);
    }

    public function toArray(): array
    {
        return [
            'type' => 'trait',
            'name' => $this->name,
            'fqn' => $this->fqn,
            'file' => $this->file,
            'line_start' => $this->lineStart,
            'line_end' => $this->lineEnd,
            'namespace' => $this->namespace,
            'methods' => array_map(fn ($m) => $m->toArray(), $this->methods),
            'properties' => array_map(fn ($p) => $p->toArray(), $this->properties),
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
            methods: array_map(fn ($m) => MethodSymbol::fromArray($m), $data['methods'] ?? []),
            properties: array_map(fn ($p) => PropertySymbol::fromArray($p), $data['properties'] ?? []),
        );
    }
}
