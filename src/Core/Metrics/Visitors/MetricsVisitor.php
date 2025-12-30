<?php

declare(strict_types=1);

namespace CodeLens\Core\Metrics\Visitors;

use CodeLens\Core\Metrics\MethodMetrics;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that collects metrics from code.
 *
 * Collects raw numerical data without making judgments.
 */
final class MetricsVisitor extends NodeVisitorAbstract
{
    private int $classCount = 0;
    private int $interfaceCount = 0;
    private int $traitCount = 0;
    private int $enumCount = 0;
    private int $methodCount = 0;
    private int $propertyCount = 0;

    /** @var array<MethodMetrics> */
    private array $methodMetrics = [];

    private ?string $currentClass = null;

    public function enterNode(Node $node): ?int
    {
        // Count classes
        if ($node instanceof Stmt\Class_) {
            $this->classCount++;
            $this->currentClass = $node->name?->toString() ?? 'anonymous';
            $this->countProperties($node);
        }

        // Count interfaces
        if ($node instanceof Stmt\Interface_) {
            $this->interfaceCount++;
            $this->currentClass = $node->name->toString();
        }

        // Count traits
        if ($node instanceof Stmt\Trait_) {
            $this->traitCount++;
            $this->currentClass = $node->name->toString();
            $this->countProperties($node);
        }

        // Count enums
        if ($node instanceof Stmt\Enum_) {
            $this->enumCount++;
            $this->currentClass = $node->name->toString();
        }

        // Collect method metrics
        if ($node instanceof Stmt\ClassMethod) {
            $this->methodCount++;
            $this->collectMethodMetrics($node);
        }

        // Count functions (standalone)
        if ($node instanceof Stmt\Function_) {
            $this->methodCount++;
            $this->collectFunctionMetrics($node);
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof Stmt\Class_ ||
            $node instanceof Stmt\Interface_ ||
            $node instanceof Stmt\Trait_ ||
            $node instanceof Stmt\Enum_) {
            $this->currentClass = null;
        }

        return null;
    }

    /**
     * Get class count.
     */
    public function getClassCount(): int
    {
        return $this->classCount;
    }

    /**
     * Get interface count.
     */
    public function getInterfaceCount(): int
    {
        return $this->interfaceCount;
    }

    /**
     * Get trait count.
     */
    public function getTraitCount(): int
    {
        return $this->traitCount;
    }

    /**
     * Get enum count.
     */
    public function getEnumCount(): int
    {
        return $this->enumCount;
    }

    /**
     * Get method count.
     */
    public function getMethodCount(): int
    {
        return $this->methodCount;
    }

    /**
     * Get property count.
     */
    public function getPropertyCount(): int
    {
        return $this->propertyCount;
    }

    /**
     * Get method metrics.
     *
     * @return array<MethodMetrics>
     */
    public function getMethodMetrics(): array
    {
        return $this->methodMetrics;
    }

    /**
     * Count properties in a class or trait.
     */
    private function countProperties(Stmt\Class_|Stmt\Trait_ $node): void
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Stmt\Property) {
                $this->propertyCount += count($stmt->props);
            }
        }
    }

    /**
     * Collect metrics for a class method.
     */
    private function collectMethodMetrics(Stmt\ClassMethod $node): void
    {
        $visibility = 'public';
        if ($node->isPrivate()) {
            $visibility = 'private';
        } elseif ($node->isProtected()) {
            $visibility = 'protected';
        }

        $lineStart = $node->getStartLine();
        $lineEnd = $node->getEndLine();
        $lineCount = $lineEnd - $lineStart + 1;

        // Count nested structures
        $nestingDepth = $this->calculateMaxNestingDepth($node->stmts ?? []);
        $conditionalCount = $this->countConditionals($node->stmts ?? []);
        $loopCount = $this->countLoops($node->stmts ?? []);
        $returnCount = $this->countReturns($node->stmts ?? []);

        $this->methodMetrics[] = new MethodMetrics(
            name: $node->name->toString(),
            parentClass: $this->currentClass ?? 'unknown',
            lineStart: $lineStart,
            lineEnd: $lineEnd,
            lineCount: $lineCount,
            maxNestingDepth: $nestingDepth,
            conditionalCount: $conditionalCount,
            loopCount: $loopCount,
            returnCount: $returnCount,
            parameterCount: count($node->params),
            visibility: $visibility,
            isStatic: $node->isStatic(),
            isAbstract: $node->isAbstract(),
        );
    }

    /**
     * Collect metrics for a standalone function.
     */
    private function collectFunctionMetrics(Stmt\Function_ $node): void
    {
        $lineStart = $node->getStartLine();
        $lineEnd = $node->getEndLine();
        $lineCount = $lineEnd - $lineStart + 1;

        $nestingDepth = $this->calculateMaxNestingDepth($node->stmts ?? []);
        $conditionalCount = $this->countConditionals($node->stmts ?? []);
        $loopCount = $this->countLoops($node->stmts ?? []);
        $returnCount = $this->countReturns($node->stmts ?? []);

        $this->methodMetrics[] = new MethodMetrics(
            name: $node->name->toString(),
            parentClass: '(function)',
            lineStart: $lineStart,
            lineEnd: $lineEnd,
            lineCount: $lineCount,
            maxNestingDepth: $nestingDepth,
            conditionalCount: $conditionalCount,
            loopCount: $loopCount,
            returnCount: $returnCount,
            parameterCount: count($node->params),
            visibility: 'public',
            isStatic: false,
            isAbstract: false,
        );
    }

    /**
     * Calculate maximum nesting depth.
     *
     * @param array<Node> $stmts
     */
    private function calculateMaxNestingDepth(array $stmts, int $currentDepth = 0): int
    {
        $maxDepth = $currentDepth;

        foreach ($stmts as $stmt) {
            $childDepth = $currentDepth;

            // These statements increase nesting
            if ($stmt instanceof Stmt\If_ ||
                $stmt instanceof Stmt\ElseIf_ ||
                $stmt instanceof Stmt\Else_ ||
                $stmt instanceof Stmt\For_ ||
                $stmt instanceof Stmt\Foreach_ ||
                $stmt instanceof Stmt\While_ ||
                $stmt instanceof Stmt\Do_ ||
                $stmt instanceof Stmt\Switch_ ||
                $stmt instanceof Stmt\TryCatch ||
                $stmt instanceof Stmt\Catch_) {
                $childDepth = $currentDepth + 1;
            }

            // Recurse into child statements
            $childStmts = $this->getChildStatements($stmt);
            if (! empty($childStmts)) {
                $nestedDepth = $this->calculateMaxNestingDepth($childStmts, $childDepth);
                $maxDepth = max($maxDepth, $nestedDepth);
            } else {
                $maxDepth = max($maxDepth, $childDepth);
            }
        }

        return $maxDepth;
    }

    /**
     * Count conditional statements.
     *
     * @param array<Node> $stmts
     */
    private function countConditionals(array $stmts): int
    {
        $count = 0;

        foreach ($stmts as $stmt) {
            // If statements
            if ($stmt instanceof Stmt\If_) {
                $count++;
                $count += count($stmt->elseifs);
                if ($stmt->else !== null) {
                    $count++;
                }
            }

            // Switch statements
            if ($stmt instanceof Stmt\Switch_) {
                $count++;
                $count += count($stmt->cases);
            }

            // Ternary in expressions
            if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\Ternary) {
                $count++;
            }

            // Null coalescing
            if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\BinaryOp\Coalesce) {
                $count++;
            }

            // Recurse into child statements
            $childStmts = $this->getChildStatements($stmt);
            if (! empty($childStmts)) {
                $count += $this->countConditionals($childStmts);
            }
        }

        return $count;
    }

    /**
     * Count loop statements.
     *
     * @param array<Node> $stmts
     */
    private function countLoops(array $stmts): int
    {
        $count = 0;

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Stmt\For_ ||
                $stmt instanceof Stmt\Foreach_ ||
                $stmt instanceof Stmt\While_ ||
                $stmt instanceof Stmt\Do_) {
                $count++;
            }

            // Recurse into child statements
            $childStmts = $this->getChildStatements($stmt);
            if (! empty($childStmts)) {
                $count += $this->countLoops($childStmts);
            }
        }

        return $count;
    }

    /**
     * Count return statements.
     *
     * @param array<Node> $stmts
     */
    private function countReturns(array $stmts): int
    {
        $count = 0;

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Stmt\Return_) {
                $count++;
            }

            // Recurse into child statements
            $childStmts = $this->getChildStatements($stmt);
            if (! empty($childStmts)) {
                $count += $this->countReturns($childStmts);
            }
        }

        return $count;
    }

    /**
     * Get child statements from a node.
     *
     * @return array<Node>
     */
    private function getChildStatements(Node $node): array
    {
        $stmts = [];

        if ($node instanceof Stmt\If_) {
            $stmts = array_merge($stmts, $node->stmts);
            foreach ($node->elseifs as $elseif) {
                $stmts = array_merge($stmts, $elseif->stmts);
            }
            if ($node->else !== null) {
                $stmts = array_merge($stmts, $node->else->stmts);
            }
        }

        if ($node instanceof Stmt\For_) {
            $stmts = array_merge($stmts, $node->stmts);
        }

        if ($node instanceof Stmt\Foreach_) {
            $stmts = array_merge($stmts, $node->stmts);
        }

        if ($node instanceof Stmt\While_) {
            $stmts = array_merge($stmts, $node->stmts);
        }

        if ($node instanceof Stmt\Do_) {
            $stmts = array_merge($stmts, $node->stmts);
        }

        if ($node instanceof Stmt\Switch_) {
            foreach ($node->cases as $case) {
                $stmts = array_merge($stmts, $case->stmts);
            }
        }

        if ($node instanceof Stmt\TryCatch) {
            $stmts = array_merge($stmts, $node->stmts);
            foreach ($node->catches as $catch) {
                $stmts = array_merge($stmts, $catch->stmts);
            }
            if ($node->finally !== null) {
                $stmts = array_merge($stmts, $node->finally->stmts);
            }
        }

        return $stmts;
    }
}
