<?php

declare(strict_types=1);

namespace CodeLens\Core\Index\Symbols;

/**
 * Represents an interface in the codebase.
 */
final readonly class InterfaceSymbol implements SymbolInterface
{
    /**
     * @param array<string> $extends
     * @param array<MethodSymbol> $methods
     */
    public function __construct(
        public string $name,
        public string $fqn,
        public string $file,
        public int $lineStart,
        public int $lineEnd,
        public ?string $namespace = null,
        public array $extends = [],
        public array $methods = []
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
        return 'interface';
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
     * Check if interface extends other interfaces.
     */
    public function hasParents(): bool
    {
        return count($this->extends) > 0;
    }

    public function toArray(): array
    {
        return [
            'type' => 'interface',
            'name' => $this->name,
            'fqn' => $this->fqn,
            'file' => $this->file,
            'line_start' => $this->lineStart,
            'line_end' => $this->lineEnd,
            'namespace' => $this->namespace,
            'extends' => $this->extends,
            'methods' => array_map(fn($m) => $m->toArray(), $this->methods),
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
            extends: $data['extends'] ?? [],
            methods: array_map(fn($m) => MethodSymbol::fromArray($m), $data['methods'] ?? [])
        );
    }
}

