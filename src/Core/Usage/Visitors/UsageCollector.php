<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage\Visitors;

use CodeLens\Core\Usage\CallReference;
use CodeLens\Core\Usage\CallType;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that collects method calls, static calls, and instantiations.
 *
 * Extracts raw call information for later resolution by the resolver chain.
 */
final class UsageCollector extends NodeVisitorAbstract
{
    private string $filePath;
    private ?string $namespace = null;

    /** @var array<string, string> Alias => FQCN */
    private array $useStatements = [];

    /** @var array<CallReference> */
    private array $references = [];

    /** @var string|null Current class being visited */
    private ?string $currentClass = null;

    /** @var string|null Current method being visited */
    private ?string $currentMethod = null;

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

        // Track current class
        if ($node instanceof Stmt\Class_ && $node->name !== null) {
            $this->currentClass = $this->buildFqn($node->name->toString());
        }

        if ($node instanceof Stmt\Interface_) {
            $this->currentClass = $this->buildFqn($node->name->toString());
        }

        if ($node instanceof Stmt\Trait_) {
            $this->currentClass = $this->buildFqn($node->name->toString());
        }

        if ($node instanceof Stmt\Enum_) {
            $this->currentClass = $this->buildFqn($node->name->toString());
        }

        // Track current method
        if ($node instanceof Stmt\ClassMethod) {
            $this->currentMethod = $node->name->toString();
        }

        if ($node instanceof Stmt\Function_) {
            $this->currentMethod = $node->name->toString();
            $this->currentClass = null; // Functions are not in a class
        }

        // Collect method calls: $obj->method()
        if ($node instanceof Expr\MethodCall) {
            $this->collectMethodCall($node);
        }

        // Collect nullsafe method calls: $obj?->method()
        if ($node instanceof Expr\NullsafeMethodCall) {
            $this->collectNullsafeMethodCall($node);
        }

        // Collect static calls: Class::method()
        if ($node instanceof Expr\StaticCall) {
            $this->collectStaticCall($node);
        }

        // Collect instantiations: new Class()
        if ($node instanceof Expr\New_) {
            $this->collectNewInstance($node);
        }

        // Collect function calls: functionName()
        if ($node instanceof Expr\FuncCall) {
            $this->collectFunctionCall($node);
        }

        // Collect closures
        if ($node instanceof Expr\Closure) {
            $this->collectClosure($node);
        }

        // Collect arrow functions
        if ($node instanceof Expr\ArrowFunction) {
            $this->collectArrowFunction($node);
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        // Reset current class when leaving
        if ($node instanceof Stmt\Class_ ||
            $node instanceof Stmt\Interface_ ||
            $node instanceof Stmt\Trait_ ||
            $node instanceof Stmt\Enum_) {
            $this->currentClass = null;
        }

        // Reset current method when leaving
        if ($node instanceof Stmt\ClassMethod || $node instanceof Stmt\Function_) {
            $this->currentMethod = null;
        }

        return null;
    }

    /**
     * Get collected references.
     *
     * @return array<CallReference>
     */
    public function getReferences(): array
    {
        return $this->references;
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
     * Collect instance method call.
     */
    private function collectMethodCall(Expr\MethodCall $node): void
    {
        if (! $node->name instanceof Node\Identifier) {
            // Dynamic method name, skip for now
            return;
        }

        $methodName = $node->name->toString();
        $calleeFqn = $this->resolveMethodCallTarget($node->var, $methodName);

        $this->addReference(
            calleeFqn: $calleeFqn,
            callType: CallType::MethodCall,
            line: $node->getStartLine(),
            context: [
                'variable_type' => $this->getVariableType($node->var),
            ],
        );
    }

    /**
     * Collect nullsafe method call.
     */
    private function collectNullsafeMethodCall(Expr\NullsafeMethodCall $node): void
    {
        if (! $node->name instanceof Node\Identifier) {
            return;
        }

        $methodName = $node->name->toString();
        $calleeFqn = $this->resolveMethodCallTarget($node->var, $methodName);

        $this->addReference(
            calleeFqn: $calleeFqn,
            callType: CallType::NullsafeCall,
            line: $node->getStartLine(),
            context: [
                'variable_type' => $this->getVariableType($node->var),
            ],
        );
    }

    /**
     * Collect static method call.
     */
    private function collectStaticCall(Expr\StaticCall $node): void
    {
        if (! $node->name instanceof Node\Identifier) {
            // Dynamic method name
            return;
        }

        $methodName = $node->name->toString();
        $className = $this->resolveStaticCallClass($node->class);

        if ($className === null) {
            return;
        }

        $calleeFqn = $className . '::' . $methodName;

        $this->addReference(
            calleeFqn: $calleeFqn,
            callType: CallType::StaticCall,
            line: $node->getStartLine(),
            context: [
                'class' => $className,
                'method' => $methodName,
            ],
        );
    }

    /**
     * Collect new instance.
     */
    private function collectNewInstance(Expr\New_ $node): void
    {
        if (! $node->class instanceof Node\Name) {
            // Anonymous class or dynamic instantiation
            return;
        }

        $className = $this->resolveTypeName($node->class->toString());

        $this->addReference(
            calleeFqn: $className,
            callType: CallType::NewInstance,
            line: $node->getStartLine(),
            context: [
                'class' => $className,
                'argument_count' => count($node->args),
            ],
        );
    }

    /**
     * Collect function call.
     */
    private function collectFunctionCall(Expr\FuncCall $node): void
    {
        if (! $node->name instanceof Node\Name) {
            // Dynamic function call
            return;
        }

        $functionName = $node->name->toString();

        // Skip built-in PHP functions
        if ($this->isBuiltInFunction($functionName)) {
            return;
        }

        $this->addReference(
            calleeFqn: $functionName,
            callType: CallType::FunctionCall,
            line: $node->getStartLine(),
            context: [
                'function' => $functionName,
            ],
        );
    }

    /**
     * Collect closure.
     */
    private function collectClosure(Expr\Closure $node): void
    {
        // Track variables used in closure
        $usedVars = [];
        foreach ($node->uses as $use) {
            $usedVars[] = '$' . $use->var->name;
        }

        if (empty($usedVars)) {
            return;
        }

        $this->addReference(
            calleeFqn: '(closure)',
            callType: CallType::Closure,
            line: $node->getStartLine(),
            confidence: 0.7,
            context: [
                'uses' => $usedVars,
            ],
        );
    }

    /**
     * Collect arrow function.
     */
    private function collectArrowFunction(Expr\ArrowFunction $node): void
    {
        // Arrow functions auto-capture by value
        // We'll track this for completeness
        $this->addReference(
            calleeFqn: '(arrow_function)',
            callType: CallType::Closure,
            line: $node->getStartLine(),
            confidence: 0.6,
            context: [
                'type' => 'arrow_function',
            ],
        );
    }

    /**
     * Add a reference to the collection.
     */
    private function addReference(
        string $calleeFqn,
        CallType $callType,
        int $line,
        float $confidence = 1.0,
        array $context = [],
    ): void {
        $callerFqn = $this->getCurrentCallerFqn();

        if ($callerFqn === null) {
            return;
        }

        $this->references[] = new CallReference(
            callerFqn: $callerFqn,
            calleeFqn: $calleeFqn,
            callType: $callType,
            file: $this->filePath,
            line: $line,
            confidence: $confidence,
            context: $context,
        );
    }

    /**
     * Get the current caller FQN.
     */
    private function getCurrentCallerFqn(): ?string
    {
        if ($this->currentClass !== null && $this->currentMethod !== null) {
            return $this->currentClass . '::' . $this->currentMethod;
        }

        if ($this->currentMethod !== null) {
            // Standalone function
            if ($this->namespace !== null) {
                return $this->namespace . '\\' . $this->currentMethod;
            }

            return $this->currentMethod;
        }

        return null;
    }

    /**
     * Resolve method call target.
     */
    private function resolveMethodCallTarget(Expr $var, string $methodName): string
    {
        // $this->method()
        if ($var instanceof Expr\Variable && $var->name === 'this') {
            if ($this->currentClass !== null) {
                return $this->currentClass . '::' . $methodName;
            }
        }

        // For other variables, we return a placeholder with the method name
        // The resolver chain will attempt to resolve the actual type
        $varType = $this->getVariableType($var);

        if ($varType !== null) {
            return $varType . '::' . $methodName;
        }

        return '(unresolved)::' . $methodName;
    }

    /**
     * Get variable type hint if available.
     */
    private function getVariableType(Expr $var): ?string
    {
        // $this
        if ($var instanceof Expr\Variable && $var->name === 'this') {
            return $this->currentClass;
        }

        // Property fetch: $this->property
        if ($var instanceof Expr\PropertyFetch) {
            // Would need type inference to resolve
            return null;
        }

        // Static property: Class::$property
        if ($var instanceof Expr\StaticPropertyFetch) {
            if ($var->class instanceof Node\Name) {
                return $this->resolveTypeName($var->class->toString());
            }
        }

        return null;
    }

    /**
     * Resolve static call class.
     */
    private function resolveStaticCallClass(Node $class): ?string
    {
        if ($class instanceof Node\Name) {
            $name = $class->toString();

            // Handle special keywords
            if ($name === 'self' || $name === 'static') {
                return $this->currentClass;
            }

            if ($name === 'parent') {
                // Would need class hierarchy to resolve
                return $this->currentClass !== null
                    ? $this->currentClass . '::parent'
                    : null;
            }

            return $this->resolveTypeName($name);
        }

        return null;
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
        if ($this->namespace !== null && ! str_contains($name, '\\')) {
            return $this->namespace . '\\' . $name;
        }

        return $name;
    }

    /**
     * Check if a function is a built-in PHP function.
     */
    private function isBuiltInFunction(string $name): bool
    {
        // Common built-in functions we don't want to track
        $builtIns = [
            'array_map', 'array_filter', 'array_reduce', 'array_walk',
            'array_merge', 'array_keys', 'array_values', 'array_flip',
            'array_unique', 'array_diff', 'array_intersect', 'array_push',
            'array_pop', 'array_shift', 'array_unshift', 'array_slice',
            'array_splice', 'array_search', 'array_key_exists', 'in_array',
            'count', 'sizeof', 'empty', 'isset', 'unset',
            'is_array', 'is_string', 'is_int', 'is_float', 'is_bool',
            'is_null', 'is_object', 'is_callable', 'is_numeric',
            'strlen', 'substr', 'strpos', 'str_replace', 'str_contains',
            'str_starts_with', 'str_ends_with', 'trim', 'ltrim', 'rtrim',
            'strtolower', 'strtoupper', 'ucfirst', 'lcfirst', 'ucwords',
            'explode', 'implode', 'join', 'sprintf', 'printf', 'sscanf',
            'preg_match', 'preg_replace', 'preg_split', 'preg_grep',
            'json_encode', 'json_decode', 'serialize', 'unserialize',
            'file_get_contents', 'file_put_contents', 'file_exists',
            'is_file', 'is_dir', 'is_readable', 'is_writable',
            'mkdir', 'rmdir', 'unlink', 'rename', 'copy', 'move_uploaded_file',
            'fopen', 'fclose', 'fread', 'fwrite', 'fgets', 'feof',
            'date', 'time', 'strtotime', 'mktime', 'gmdate', 'microtime',
            'print_r', 'var_dump', 'var_export', 'debug_backtrace',
            'class_exists', 'interface_exists', 'trait_exists', 'method_exists',
            'property_exists', 'function_exists', 'get_class', 'get_parent_class',
            'defined', 'define', 'constant',
            'abs', 'ceil', 'floor', 'round', 'max', 'min', 'pow', 'sqrt',
            'rand', 'mt_rand', 'random_int', 'random_bytes',
            'header', 'setcookie', 'session_start', 'session_destroy',
            'exit', 'die', 'sleep', 'usleep',
            'compact', 'extract', 'list', 'each',
            'call_user_func', 'call_user_func_array',
            'array_column', 'array_combine', 'array_chunk', 'array_fill',
            'array_multisort', 'array_reverse', 'array_sum', 'array_product',
            'sort', 'rsort', 'asort', 'arsort', 'ksort', 'krsort', 'usort',
            'base64_encode', 'base64_decode', 'urlencode', 'urldecode',
            'hash', 'hash_file', 'md5', 'sha1', 'password_hash', 'password_verify',
            'trigger_error', 'set_error_handler', 'set_exception_handler',
            'getenv', 'putenv', 'php_sapi_name', 'phpversion', 'phpinfo',
            'assert',
        ];

        return in_array(strtolower($name), $builtIns, true);
    }
}
