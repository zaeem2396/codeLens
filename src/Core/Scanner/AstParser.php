<?php

declare(strict_types=1);

namespace CodeLens\Core\Scanner;

use CodeLens\Core\Scanner\Visitors\SymbolCollector;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\Error as ParseError;

/**
 * Wrapper around nikic/php-parser for AST parsing.
 * 
 * Provides a simplified interface for parsing PHP files
 * and extracting symbol information.
 */
final class AstParser
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * Parse a PHP file and extract symbols.
     * 
     * @return ParseResult The parsing result with extracted symbols
     */
    public function parseFile(string $filePath): ParseResult
    {
        $code = @file_get_contents($filePath);
        
        if ($code === false) {
            return ParseResult::error($filePath, 'Could not read file');
        }

        return $this->parseCode($code, $filePath);
    }

    /**
     * Parse PHP code and extract symbols.
     * 
     * @return ParseResult The parsing result with extracted symbols
     */
    public function parseCode(string $code, string $filePath = 'unknown'): ParseResult
    {
        try {
            $ast = $this->parser->parse($code);
            
            if ($ast === null) {
                return ParseResult::error($filePath, 'Parser returned null');
            }

            $collector = new SymbolCollector($filePath);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($collector);
            $traverser->traverse($ast);

            return ParseResult::success(
                filePath: $filePath,
                symbols: $collector->getSymbols(),
                namespace: $collector->getNamespace(),
                useStatements: $collector->getUseStatements()
            );
        } catch (ParseError $e) {
            return ParseResult::error($filePath, 'Parse error: ' . $e->getMessage());
        } catch (\Throwable $e) {
            return ParseResult::error($filePath, 'Unexpected error: ' . $e->getMessage());
        }
    }
}

