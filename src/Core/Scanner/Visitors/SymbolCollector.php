<?php

declare(strict_types=1);

namespace CodeLens\Core\Scanner\Visitors;

use CodeLens\Core\Index\Symbols\ClassSymbol;
use CodeLens\Core\Index\Symbols\EnumSymbol;
use CodeLens\Core\Index\Symbols\InterfaceSymbol;
use CodeLens\Core\Index\Symbols\MethodSymbol;
use CodeLens\Core\Index\Symbols\PropertySymbol;
use CodeLens\Core\Index\Symbols\SymbolInterface;
use CodeLens\Core\Index\Symbols\TraitSymbol;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that collects symbol information.
 * 
 * Extracts classes, interfaces, traits, enums, methods,
 * and properties from the AST.
 */
final class SymbolCollector extends NodeVisitorAbstract
{
    private string $filePath;
    private ?string $namespace = null;
    
    /** @var array<string, string> Alias => FQCN */
    private array $useStatements = [];
    
    /** @var array<SymbolInterface> */
    private array $symbols = [];

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function enterNode(Node $node): ?int
    {
        // Capture namespace
        if ($node instanceof Stmt\Namespace_) {
            $this->namespace = $node->name?->toString();
        }

        // Capture use statements
        if ($node instanceof Stmt\Use_) {
            foreach ($node->uses as $use) {
                $alias = $use->alias?->toString() ?? $use->name->getLast();
                $this->useStatements[$alias] = $use->name->toString();
            }
        }

        // Capture class
        if ($node instanceof Stmt\Class_) {
            $this->collectClass($node);
        }

        // Capture interface
        if ($node instanceof Stmt\Interface_) {
            $this->collectInterface($node);
        }

        // Capture trait
        if ($node instanceof Stmt\Trait_) {
            $this->collectTrait($node);
        }

        // Capture enum
        if ($node instanceof Stmt\Enum_) {
            $this->collectEnum($node);
        }

        return null;
    }

    /**
     * Get collected symbols.
     * 
     * @return array<SymbolInterface>
     */
    public function getSymbols(): array
    {
        return $this->symbols;
    }

    /**
     * Get the namespace.
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * Get use statements.
     * 
     * @return array<string, string>
     */
    public function getUseStatements(): array
    {
        return $this->useStatements;
    }

    /**
     * Collect class information.
     */
    private function collectClass(Stmt\Class_ $node): void
    {
        if ($node->name === null) {
            return; // Anonymous class
        }

        $fqn = $this->buildFqn($node->name->toString());
        
        $extends = null;
        if ($node->extends !== null) {
            $extends = $this->resolveTypeName($node->extends->toString());
        }

        $implements = [];
        foreach ($node->implements as $interface) {
            $implements[] = $this->resolveTypeName($interface->toString());
        }

        $traits = [];
        $methods = [];
        $properties = [];

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Stmt\TraitUse) {
                foreach ($stmt->traits as $trait) {
                    $traits[] = $this->resolveTypeName($trait->toString());
                }
            }

            if ($stmt instanceof Stmt\ClassMethod) {
                $methods[] = $this->collectMethod($stmt, $fqn);
            }

            if ($stmt instanceof Stmt\Property) {
                foreach ($stmt->props as $prop) {
                    $properties[] = $this->collectProperty($prop, $stmt, $fqn);
                }
            }
        }

        $this->symbols[] = new ClassSymbol(
            name: $node->name->toString(),
            fqn: $fqn,
            file: $this->filePath,
            lineStart: $node->getStartLine(),
            lineEnd: $node->getEndLine(),
            namespace: $this->namespace,
            extends: $extends,
            implements: $implements,
            traits: $traits,
            methods: $methods,
            properties: $properties,
            isAbstract: $node->isAbstract(),
            isFinal: $node->isFinal(),
            isReadonly: $node->isReadonly()
        );
    }

    /**
     * Collect interface information.
     */
    private function collectInterface(Stmt\Interface_ $node): void
    {
        $fqn = $this->buildFqn($node->name->toString());

        $extends = [];
        foreach ($node->extends as $parent) {
            $extends[] = $this->resolveTypeName($parent->toString());
        }

        $methods = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Stmt\ClassMethod) {
                $methods[] = $this->collectMethod($stmt, $fqn);
            }
        }

        $this->symbols[] = new InterfaceSymbol(
            name: $node->name->toString(),
            fqn: $fqn,
            file: $this->filePath,
            lineStart: $node->getStartLine(),
            lineEnd: $node->getEndLine(),
            namespace: $this->namespace,
            extends: $extends,
            methods: $methods
        );
    }

    /**
     * Collect trait information.
     */
    private function collectTrait(Stmt\Trait_ $node): void
    {
        $fqn = $this->buildFqn($node->name->toString());

        $methods = [];
        $properties = [];

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Stmt\ClassMethod) {
                $methods[] = $this->collectMethod($stmt, $fqn);
            }

            if ($stmt instanceof Stmt\Property) {
                foreach ($stmt->props as $prop) {
                    $properties[] = $this->collectProperty($prop, $stmt, $fqn);
                }
            }
        }

        $this->symbols[] = new TraitSymbol(
            name: $node->name->toString(),
            fqn: $fqn,
            file: $this->filePath,
            lineStart: $node->getStartLine(),
            lineEnd: $node->getEndLine(),
            namespace: $this->namespace,
            methods: $methods,
            properties: $properties
        );
    }

    /**
     * Collect enum information.
     */
    private function collectEnum(Stmt\Enum_ $node): void
    {
        $fqn = $this->buildFqn($node->name->toString());

        $implements = [];
        foreach ($node->implements as $interface) {
            $implements[] = $this->resolveTypeName($interface->toString());
        }

        $cases = [];
        $methods = [];

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Stmt\EnumCase) {
                $cases[] = $stmt->name->toString();
            }

            if ($stmt instanceof Stmt\ClassMethod) {
                $methods[] = $this->collectMethod($stmt, $fqn);
            }
        }

        $scalarType = null;
        if ($node->scalarType !== null) {
            $scalarType = $node->scalarType->toString();
        }

        $this->symbols[] = new EnumSymbol(
            name: $node->name->toString(),
            fqn: $fqn,
            file: $this->filePath,
            lineStart: $node->getStartLine(),
            lineEnd: $node->getEndLine(),
            namespace: $this->namespace,
            implements: $implements,
            cases: $cases,
            methods: $methods,
            scalarType: $scalarType
        );
    }

    /**
     * Collect method information.
     */
    private function collectMethod(Stmt\ClassMethod $node, string $parentFqn): MethodSymbol
    {
        $visibility = 'public';
        if ($node->isPrivate()) {
            $visibility = 'private';
        } elseif ($node->isProtected()) {
            $visibility = 'protected';
        }

        $parameters = [];
        foreach ($node->params as $param) {
            $paramType = null;
            if ($param->type !== null) {
                $paramType = $this->nodeTypeToString($param->type);
            }
            
            $parameters[] = [
                'name' => '$' . $param->var->name,
                'type' => $paramType,
                'default' => $param->default !== null,
                'byRef' => $param->byRef,
                'variadic' => $param->variadic,
            ];
        }

        $returnType = null;
        if ($node->returnType !== null) {
            $returnType = $this->nodeTypeToString($node->returnType);
        }

        return new MethodSymbol(
            name: $node->name->toString(),
            parentFqn: $parentFqn,
            file: $this->filePath,
            lineStart: $node->getStartLine(),
            lineEnd: $node->getEndLine(),
            visibility: $visibility,
            isStatic: $node->isStatic(),
            isAbstract: $node->isAbstract(),
            isFinal: $node->isFinal(),
            returnType: $returnType,
            parameters: $parameters
        );
    }

    /**
     * Collect property information.
     */
    private function collectProperty(
        Node\Stmt\PropertyProperty $prop,
        Stmt\Property $stmt,
        string $parentFqn
    ): PropertySymbol {
        $visibility = 'public';
        if ($stmt->isPrivate()) {
            $visibility = 'private';
        } elseif ($stmt->isProtected()) {
            $visibility = 'protected';
        }

        $type = null;
        if ($stmt->type !== null) {
            $type = $this->nodeTypeToString($stmt->type);
        }

        return new PropertySymbol(
            name: '$' . $prop->name->toString(),
            parentFqn: $parentFqn,
            file: $this->filePath,
            line: $prop->getStartLine(),
            visibility: $visibility,
            type: $type,
            isStatic: $stmt->isStatic(),
            isReadonly: $stmt->isReadonly(),
            hasDefault: $prop->default !== null
        );
    }

    /**
     * Build fully qualified name.
     */
    private function buildFqn(string $name): string
    {
        if ($this->namespace !== null) {
            return $this->namespace . '\\' . $name;
        }

        return $name;
    }

    /**
     * Resolve a type name using use statements.
     */
    private function resolveTypeName(string $name): string
    {
        // Already fully qualified
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        // Check use statements
        $parts = explode('\\', $name);
        $first = $parts[0];

        if (isset($this->useStatements[$first])) {
            $parts[0] = $this->useStatements[$first];
            return implode('\\', $parts);
        }

        // Assume same namespace
        if ($this->namespace !== null) {
            return $this->namespace . '\\' . $name;
        }

        return $name;
    }

    /**
     * Convert node type to string.
     */
    private function nodeTypeToString(Node $type): string
    {
        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }

        if ($type instanceof Node\Name) {
            return $this->resolveTypeName($type->toString());
        }

        if ($type instanceof Node\NullableType) {
            return '?' . $this->nodeTypeToString($type->type);
        }

        if ($type instanceof Node\UnionType) {
            $types = array_map(fn($t) => $this->nodeTypeToString($t), $type->types);
            return implode('|', $types);
        }

        if ($type instanceof Node\IntersectionType) {
            $types = array_map(fn($t) => $this->nodeTypeToString($t), $type->types);
            return implode('&', $types);
        }

        return 'mixed';
    }
}

