<?php

declare(strict_types=1);

namespace CodeLens\Core\Index\Symbols;

/**
 * Represents a class in the codebase.
 */
final readonly class ClassSymbol implements SymbolInterface
{
    /**
     * @param array<MethodSymbol> $methods
     * @param array<PropertySymbol> $properties
     * @param array<string> $implements
     * @param array<string> $traits
     */
    public function __construct(
        public string $name,
        public string $fqn,
        public string $file,
        public int $lineStart,
        public int $lineEnd,
        public ?string $namespace = null,
        public ?string $extends = null,
        public array $implements = [],
        public array $traits = [],
        public array $methods = [],
        public array $properties = [],
        public bool $isAbstract = false,
        public bool $isFinal = false,
        public bool $isReadonly = false
    ) {}

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
        return 'class';
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

    /**
     * Check if class has parent.
     */
    public function hasParent(): bool
    {
        return $this->extends !== null;
    }

    /**
     * Check if class implements interfaces.
     */
    public function hasInterfaces(): bool
    {
        return count($this->implements) > 0;
    }

    /**
     * Check if class uses traits.
     */
    public function hasTraits(): bool
    {
        return count($this->traits) > 0;
    }

    public function toArray(): array
    {
        return [
            'type' => 'class',
            'name' => $this->name,
            'fqn' => $this->fqn,
            'file' => $this->file,
            'line_start' => $this->lineStart,
            'line_end' => $this->lineEnd,
            'namespace' => $this->namespace,
            'extends' => $this->extends,
            'implements' => $this->implements,
            'traits' => $this->traits,
            'methods' => array_map(fn($m) => $m->toArray(), $this->methods),
            'properties' => array_map(fn($p) => $p->toArray(), $this->properties),
            'is_abstract' => $this->isAbstract,
            'is_final' => $this->isFinal,
            'is_readonly' => $this->isReadonly,
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
            extends: $data['extends'] ?? null,
            implements: $data['implements'] ?? [],
            traits: $data['traits'] ?? [],
            methods: array_map(fn($m) => MethodSymbol::fromArray($m), $data['methods'] ?? []),
            properties: array_map(fn($p) => PropertySymbol::fromArray($p), $data['properties'] ?? []),
            isAbstract: $data['is_abstract'] ?? false,
            isFinal: $data['is_final'] ?? false,
            isReadonly: $data['is_readonly'] ?? false
        );
    }
}

