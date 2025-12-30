<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->exclude('vendor');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP81Migration' => true,
        
        // Imports
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        
        // Arrays
        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        
        // Spacing
        'binary_operator_spaces' => ['default' => 'single_space'],
        'concat_space' => ['spacing' => 'one'],
        'not_operator_with_successor_space' => true,
        
        // Strict
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        
        // Clean code
        'no_empty_statement' => true,
        'no_extra_blank_lines' => true,
        'no_trailing_whitespace' => true,
        'single_blank_line_at_eof' => true,
        
        // PHPDoc
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_order' => true,
        'phpdoc_separation' => true,
        'phpdoc_trim' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
    ])
    ->setFinder($finder);

