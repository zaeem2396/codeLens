<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Index\SymbolRegistry;
use CodeLens\Core\Scanner\FileDiscovery;
use CodeLens\Core\Usage\Resolvers\ClosureResolver;
use CodeLens\Core\Usage\Resolvers\ConstructorResolver;
use CodeLens\Core\Usage\Resolvers\DirectCallResolver;
use CodeLens\Core\Usage\Resolvers\FrameworkResolver;
use CodeLens\Core\Usage\Resolvers\InterfaceResolver;
use CodeLens\Core\Usage\Resolvers\ResolverInterface;
use CodeLens\Core\Usage\Storage\UsageStorage;
use CodeLens\Core\Usage\Visitors\UsageCollector;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Throwable;

/**
 * Main orchestrator for usage analysis.
 *
 * Coordinates file discovery, AST parsing, usage collection,
 * and resolution to build a complete call graph.
 */
final class UsageAnalyzer
{
    private Configuration $config;
    private string $basePath;
    private SymbolRegistry $symbolRegistry;
    private UsageStorage $storage;
    private Parser $parser;
    private FileDiscovery $fileDiscovery;

    /** @var ResolverInterface[] */
    private array $resolvers = [];

    /** @var callable|null */
    private $progressCallback = null;

    public function __construct(
        Configuration $config,
        string $basePath,
        SymbolRegistry $symbolRegistry,
        UsageStorage $storage,
    ) {
        $this->config = $config;
        $this->basePath = $basePath;
        $this->symbolRegistry = $symbolRegistry;
        $this->storage = $storage;
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->fileDiscovery = new FileDiscovery($config, $basePath);

        $this->registerDefaultResolvers();
    }

    /**
     * Register default resolvers.
     */
    private function registerDefaultResolvers(): void
    {
        $this->registerResolver(new DirectCallResolver());
        $this->registerResolver(new ConstructorResolver());
        $this->registerResolver(new InterfaceResolver());
        $this->registerResolver(new ClosureResolver());
        $this->registerResolver(new FrameworkResolver());
    }

    /**
     * Register a custom resolver.
     */
    public function registerResolver(ResolverInterface $resolver): void
    {
        $this->resolvers[$resolver->getId()] = $resolver;

        // Sort by priority (higher first)
        uasort($this->resolvers, fn ($a, $b) => $b->getPriority() <=> $a->getPriority());
    }

    /**
     * Remove a resolver.
     */
    public function removeResolver(string $resolverId): void
    {
        unset($this->resolvers[$resolverId]);
    }

    /**
     * Set progress callback.
     *
     * @param callable(string $file, int $current, int $total): void $callback
     */
    public function onProgress(callable $callback): self
    {
        $this->progressCallback = $callback;

        return $this;
    }

    /**
     * Analyze usage across the codebase.
     */
    public function analyze(): UsageResult
    {
        $startTime = microtime(true);

        // Discover files
        $files = $this->fileDiscovery->discover();
        $totalFiles = count($files);
        $current = 0;
        $errors = [];

        $callGraph = new CallGraph();

        foreach ($files as $path => $fileInfo) {
            $current++;

            if ($this->progressCallback !== null) {
                ($this->progressCallback)($fileInfo->relativePath, $current, $totalFiles);
            }

            try {
                $references = $this->analyzeFile($path);
                $callGraph->addReferences($references);
            } catch (Throwable $e) {
                $errors[$path] = $e->getMessage();
            }
        }

        // Resolve all references
        $resolvedGraph = $this->resolveReferences($callGraph);

        $duration = microtime(true) - $startTime;

        $result = new UsageResult(
            callGraph: $resolvedGraph,
            duration: $duration,
            filesAnalyzed: $totalFiles,
            errors: $errors,
        );

        // Save results
        $this->storage->saveCallGraph($resolvedGraph);
        $this->storage->saveMetadata([
            'last_analysis' => date('c'),
            'duration_seconds' => round($duration, 3),
            'files_analyzed' => $totalFiles,
            'total_references' => $resolvedGraph->count(),
            'error_count' => count($errors),
        ]);

        return $result;
    }

    /**
     * Analyze a specific path.
     */
    public function analyzePath(string $path): UsageResult
    {
        $startTime = microtime(true);

        // Discover files in path
        $files = $this->fileDiscovery->discoverInPath($path);
        $totalFiles = count($files);
        $current = 0;
        $errors = [];

        $callGraph = new CallGraph();

        foreach ($files as $filePath => $fileInfo) {
            $current++;

            if ($this->progressCallback !== null) {
                ($this->progressCallback)($fileInfo->relativePath, $current, $totalFiles);
            }

            try {
                $references = $this->analyzeFile($filePath);
                $callGraph->addReferences($references);
            } catch (Throwable $e) {
                $errors[$filePath] = $e->getMessage();
            }
        }

        // Resolve references
        $resolvedGraph = $this->resolveReferences($callGraph);

        $duration = microtime(true) - $startTime;

        return new UsageResult(
            callGraph: $resolvedGraph,
            duration: $duration,
            filesAnalyzed: $totalFiles,
            errors: $errors,
        );
    }

    /**
     * Analyze a single file.
     *
     * @return array<CallReference>
     */
    public function analyzeFile(string $filePath): array
    {
        $code = @file_get_contents($filePath);

        if ($code === false) {
            return [];
        }

        try {
            $ast = $this->parser->parse($code);

            if ($ast === null) {
                return [];
            }

            $collector = new UsageCollector($filePath);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($collector);
            $traverser->traverse($ast);

            return $collector->getReferences();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Resolve references using the resolver chain.
     */
    private function resolveReferences(CallGraph $graph): CallGraph
    {
        $resolvedGraph = new CallGraph();

        foreach ($graph->getAllReferences() as $reference) {
            $resolved = $this->resolveReference($reference);
            $resolvedGraph->addReference($resolved);
        }

        return $resolvedGraph;
    }

    /**
     * Resolve a single reference through the resolver chain.
     */
    private function resolveReference(CallReference $reference): CallReference
    {
        $current = $reference;

        foreach ($this->resolvers as $resolver) {
            if ($resolver->canResolve($current)) {
                $current = $resolver->resolve($current, $this->symbolRegistry);
            }
        }

        return $current;
    }

    /**
     * Get callers of a method/class.
     */
    public function getCallersOf(string $fqn): array
    {
        return $this->storage->getCallersOf($fqn);
    }

    /**
     * Get calls from a method/class.
     */
    public function getCallsFrom(string $fqn): array
    {
        return $this->storage->getCallsFrom($fqn);
    }

    /**
     * Get all references for a class.
     */
    public function getClassUsage(string $classFqn): array
    {
        return [
            'incoming' => $this->storage->getClassReferences($classFqn),
            'outgoing' => $this->getOutgoingForClass($classFqn),
        ];
    }

    /**
     * Get outgoing calls for all methods of a class.
     */
    private function getOutgoingForClass(string $classFqn): array
    {
        $graph = $this->storage->loadCallGraph();

        return $graph->getOutgoingCallsForClass($classFqn);
    }

    /**
     * Get entry points (methods with no callers).
     */
    public function getEntryPoints(): array
    {
        return $this->storage->getEntryPoints();
    }

    /**
     * Get usage summary for a FQN.
     */
    public function getSummary(string $fqn): ?array
    {
        return $this->storage->getSummary($fqn);
    }

    /**
     * Get all summaries.
     */
    public function getAllSummaries(): array
    {
        return $this->storage->getAllSummaries();
    }

    /**
     * Get statistics.
     */
    public function getStats(): array
    {
        return $this->storage->getStats();
    }

    /**
     * Check if analysis data exists.
     */
    public function hasData(): bool
    {
        return $this->storage->hasData();
    }

    /**
     * Clear all analysis data.
     */
    public function clear(): void
    {
        $this->storage->clear();
    }

    /**
     * Get configuration.
     */
    public function getConfiguration(): Configuration
    {
        return $this->config;
    }

    /**
     * Get base path.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get registered resolvers.
     *
     * @return ResolverInterface[]
     */
    public function getResolvers(): array
    {
        return array_values($this->resolvers);
    }
}
