<?php

declare(strict_types=1);

namespace CodeLens\Core\Metrics;

use CodeLens\Core\Metrics\Visitors\MetricsVisitor;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Throwable;

/**
 * Calculates metrics for PHP files.
 *
 * Produces raw numerical data without any judgments.
 */
final class MetricsCalculator
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * Calculate metrics for a file.
     */
    public function calculateForFile(string $filePath, string $relativePath = ''): ?FileMetrics
    {
        $code = @file_get_contents($filePath);

        if ($code === false) {
            return null;
        }

        if ($relativePath === '') {
            $relativePath = basename($filePath);
        }

        return $this->calculateForCode($code, $filePath, $relativePath);
    }

    /**
     * Calculate metrics for code.
     */
    public function calculateForCode(string $code, string $filePath, string $relativePath): ?FileMetrics
    {
        try {
            $ast = $this->parser->parse($code);

            if ($ast === null) {
                return null;
            }

            // Calculate line metrics
            $lineMetrics = $this->calculateLineMetrics($code);

            // Use visitor to collect AST metrics
            $visitor = new MetricsVisitor();
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            return new FileMetrics(
                filePath: $filePath,
                relativePath: $relativePath,
                linesOfCode: $lineMetrics['total'],
                linesOfCodeWithoutComments: $lineMetrics['code'],
                blankLines: $lineMetrics['blank'],
                commentLines: $lineMetrics['comment'],
                classCount: $visitor->getClassCount(),
                interfaceCount: $visitor->getInterfaceCount(),
                traitCount: $visitor->getTraitCount(),
                enumCount: $visitor->getEnumCount(),
                methodCount: $visitor->getMethodCount(),
                propertyCount: $visitor->getPropertyCount(),
                methodMetrics: $visitor->getMethodMetrics(),
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Calculate line-based metrics.
     *
     * @return array{total: int, code: int, blank: int, comment: int}
     */
    private function calculateLineMetrics(string $code): array
    {
        $lines = explode("\n", $code);
        $total = count($lines);
        $blank = 0;
        $comment = 0;
        $inMultilineComment = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Blank line
            if ($trimmed === '') {
                $blank++;

                continue;
            }

            // Multi-line comment handling
            if ($inMultilineComment) {
                $comment++;
                if (str_contains($trimmed, '*/')) {
                    $inMultilineComment = false;
                }

                continue;
            }

            // Start of multi-line comment
            if (str_starts_with($trimmed, '/*')) {
                $comment++;
                if (! str_contains($trimmed, '*/')) {
                    $inMultilineComment = true;
                }

                continue;
            }

            // Single-line comment
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
                $comment++;

                continue;
            }

            // PHPDoc style
            if (str_starts_with($trimmed, '*')) {
                $comment++;

                continue;
            }
        }

        return [
            'total' => $total,
            'code' => $total - $blank - $comment,
            'blank' => $blank,
            'comment' => $comment,
        ];
    }
}
