<?php

declare(strict_types=1);

namespace CodeLens\Core\Index\Symbols;

/**
 * Represents a method in a class, interface, trait, or enum.
 */
final class MethodSymbol implements SymbolInterface
{
    /**
     * @param array<array{name: string, type: ?string, default: bool, byRef: bool, variadic: bool}> $parameters
     */
    public function __construct(
        public readonly string $name,
        public readonly string $parentFqn,
        public readonly string $file,
        public readonly int $lineStart,
        public readonly int $lineEnd,
        public readonly string $visibility = 'public',
        public readonly bool $isStatic = false,
        public readonly bool $isAbstract = false,
        public readonly bool $isFinal = false,
        public readonly ?string $returnType = null,
        public readonly array $parameters = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFqn(): string
    {
        return $this->parentFqn . '::' . $this->name;
    }

    public function getType(): string
    {
        return 'method';
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
     * Get the line count of this method.
     */
    public function getLineCount(): int
    {
        return $this->lineEnd - $this->lineStart + 1;
    }

    /**
     * Get parameter count.
     */
    public function getParameterCount(): int
    {
        return count($this->parameters);
    }

    /**
     * Check if method is public.
     */
    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    /**
     * Check if method is protected.
     */
    public function isProtected(): bool
    {
        return $this->visibility === 'protected';
    }

    /**
     * Check if method is private.
     */
    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    /**
     * Check if method has return type.
     */
    public function hasReturnType(): bool
    {
        return $this->returnType !== null;
    }

    public function toArray(): array
    {
        return [
            'type' => 'method',
            'name' => $this->name,
            'parent_fqn' => $this->parentFqn,
            'file' => $this->file,
            'line_start' => $this->lineStart,
            'line_end' => $this->lineEnd,
            'visibility' => $this->visibility,
            'is_static' => $this->isStatic,
            'is_abstract' => $this->isAbstract,
            'is_final' => $this->isFinal,
            'return_type' => $this->returnType,
            'parameters' => $this->parameters,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            parentFqn: $data['parent_fqn'],
            file: $data['file'],
            lineStart: $data['line_start'],
            lineEnd: $data['line_end'],
            visibility: $data['visibility'] ?? 'public',
            isStatic: $data['is_static'] ?? false,
            isAbstract: $data['is_abstract'] ?? false,
            isFinal: $data['is_final'] ?? false,
            returnType: $data['return_type'] ?? null,
            parameters: $data['parameters'] ?? [],
        );
    }
}
