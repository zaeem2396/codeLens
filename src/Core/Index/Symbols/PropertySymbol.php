<?php

declare(strict_types=1);

namespace CodeLens\Core\Index\Symbols;

/**
 * Represents a property in a class or trait.
 */
final class PropertySymbol implements SymbolInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $parentFqn,
        public readonly string $file,
        public readonly int $line,
        public readonly string $visibility = 'public',
        public readonly ?string $type = null,
        public readonly bool $isStatic = false,
        public readonly bool $isReadonly = false,
        public readonly bool $hasDefault = false,
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
        return 'property';
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLineStart(): int
    {
        return $this->line;
    }

    public function getLineEnd(): int
    {
        return $this->line;
    }

    /**
     * Check if property is public.
     */
    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    /**
     * Check if property is protected.
     */
    public function isProtected(): bool
    {
        return $this->visibility === 'protected';
    }

    /**
     * Check if property is private.
     */
    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    /**
     * Check if property has type declaration.
     */
    public function hasType(): bool
    {
        return $this->type !== null;
    }

    public function toArray(): array
    {
        return [
            'type' => 'property',
            'name' => $this->name,
            'parent_fqn' => $this->parentFqn,
            'file' => $this->file,
            'line' => $this->line,
            'visibility' => $this->visibility,
            'declared_type' => $this->type,
            'is_static' => $this->isStatic,
            'is_readonly' => $this->isReadonly,
            'has_default' => $this->hasDefault,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            parentFqn: $data['parent_fqn'],
            file: $data['file'],
            line: $data['line'],
            visibility: $data['visibility'] ?? 'public',
            type: $data['declared_type'] ?? null,
            isStatic: $data['is_static'] ?? false,
            isReadonly: $data['is_readonly'] ?? false,
            hasDefault: $data['has_default'] ?? false,
        );
    }
}
