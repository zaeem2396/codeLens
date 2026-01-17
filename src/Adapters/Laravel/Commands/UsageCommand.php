<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Laravel\Commands;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Storage\JsonStorage;
use CodeLens\Core\Usage\CallType;
use CodeLens\Core\Usage\Storage\UsageStorage;
use CodeLens\Core\Usage\UsageAnalyzer;
use Illuminate\Console\Command;

/**
 * Laravel Artisan command for usage analysis.
 */
class UsageCommand extends Command
{
    protected $signature = 'codelens:usage
        {--path= : Path to analyze (defaults to configured paths)}
        {--show= : Show usage for a specific class}
        {--callers= : Show who calls a specific method/class}
        {--calls= : Show what a specific method/class calls}
        {--entry-points : Show potential entry points}
        {--stats : Show usage statistics only}
        {--fresh : Force fresh analysis (ignore cache)}
        {--json : Output results as JSON}';

    protected $description = 'Analyze code usage and dependencies';

    public function handle(): int
    {
        $configPath = base_path('codelens.php');
        $config = file_exists($configPath)
            ? Configuration::fromFile($configPath)
            : new Configuration();

        $basePath = base_path();
        $storagePath = $basePath . '/.codelens';

        // Load symbol registry
        $jsonStorage = new JsonStorage($storagePath);

        if (! $jsonStorage->hasData()) {
            $this->error('No scan data found. Run "php artisan codelens:scan" first.');

            return Command::FAILURE;
        }

        $symbolRegistry = $jsonStorage->loadSymbolRegistry();
        $usageStorage = new UsageStorage($storagePath);

        $analyzer = new UsageAnalyzer($config, $basePath, $symbolRegistry, $usageStorage);

        // Handle --show option
        if ($fqn = $this->option('show')) {
            return $this->showClassUsage($analyzer, $fqn);
        }

        // Handle --callers option
        if ($fqn = $this->option('callers')) {
            return $this->showCallers($analyzer, $fqn);
        }

        // Handle --calls option
        if ($fqn = $this->option('calls')) {
            return $this->showCalls($analyzer, $fqn);
        }

        // Handle --entry-points option
        if ($this->option('entry-points')) {
            return $this->showEntryPoints($analyzer);
        }

        // Handle --stats option
        if ($this->option('stats')) {
            return $this->showStats($analyzer);
        }

        // Run full analysis
        return $this->runAnalysis($analyzer);
    }

    /**
     * Run full usage analysis.
     */
    private function runAnalysis(UsageAnalyzer $analyzer): int
    {
        $fresh = $this->option('fresh');

        if (! $fresh && $analyzer->hasData()) {
            $this->info('Using cached analysis data. Use --fresh to re-analyze.');

            return $this->showStats($analyzer);
        }

        $this->info('Analyzing code usage...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar();
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        $analyzer->onProgress(function (string $file, int $current, int $total) use ($progressBar) {
            $progressBar->setMaxSteps($total);
            $progressBar->setProgress($current);
            $progressBar->setMessage($file);
        });

        $result = $analyzer->analyze();

        $progressBar->finish();
        $this->newLine(2);

        // JSON output
        if ($this->option('json')) {
            $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        // Display summary
        $summary = $result->getSummary();

        $this->info('=== Usage Analysis Complete ===');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Files Analyzed', $summary['files_analyzed']],
                ['Total References', $summary['total_references']],
                ['Unique Callers', $summary['unique_callers']],
                ['Unique Callees', $summary['unique_callees']],
                ['Entry Points', $summary['entry_points']],
                ['Duration', $summary['duration']],
            ],
        );

        // By type breakdown
        $this->newLine();
        $this->info('References by Type:');

        $typeRows = [];
        foreach ($summary['by_type'] as $type => $count) {
            if ($count > 0) {
                $typeRows[] = [CallType::from($type)->label(), $count];
            }
        }
        $this->table(['Type', 'Count'], $typeRows);

        if ($result->hasErrors()) {
            $this->newLine();
            $this->warn("Encountered {$result->getErrorCount()} errors during analysis.");
        }

        return Command::SUCCESS;
    }

    /**
     * Show usage for a specific class.
     */
    private function showClassUsage(UsageAnalyzer $analyzer, string $fqn): int
    {
        if (! $analyzer->hasData()) {
            $this->error('No usage data found. Run "php artisan codelens:usage" first.');

            return Command::FAILURE;
        }

        $usage = $analyzer->getClassUsage($fqn);

        if ($this->option('json')) {
            $this->line(json_encode([
                'class' => $fqn,
                'incoming' => array_map(fn ($r) => $r->toArray(), $usage['incoming']),
                'outgoing' => array_map(fn ($r) => $r->toArray(), $usage['outgoing']),
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info("=== Usage for {$fqn} ===");
        $this->newLine();

        // Incoming calls
        $this->info('Incoming Calls (' . count($usage['incoming']) . '):');
        if (empty($usage['incoming'])) {
            $this->line('  <fg=gray>No incoming calls found</>');
        } else {
            foreach ($usage['incoming'] as $ref) {
                $confidence = $this->formatConfidence($ref->confidence);
                $this->line("  ← {$ref->callerFqn} {$confidence}");
                $this->line("    <fg=gray>{$ref->file}:{$ref->line}</>");
            }
        }

        $this->newLine();

        // Outgoing calls
        $this->info('Outgoing Calls (' . count($usage['outgoing']) . '):');
        if (empty($usage['outgoing'])) {
            $this->line('  <fg=gray>No outgoing calls found</>');
        } else {
            foreach ($usage['outgoing'] as $ref) {
                $confidence = $this->formatConfidence($ref->confidence);
                $this->line("  → {$ref->calleeFqn} {$confidence}");
                $this->line("    <fg=gray>{$ref->file}:{$ref->line}</>");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Show callers of a method/class.
     */
    private function showCallers(UsageAnalyzer $analyzer, string $fqn): int
    {
        if (! $analyzer->hasData()) {
            $this->error('No usage data found. Run "php artisan codelens:usage" first.');

            return Command::FAILURE;
        }

        $callers = $analyzer->getCallersOf($fqn);

        if ($this->option('json')) {
            $this->line(json_encode([
                'target' => $fqn,
                'callers' => array_map(fn ($r) => $r->toArray(), $callers),
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info("=== Who calls {$fqn}? ===");
        $this->newLine();

        if (empty($callers)) {
            $this->line('<fg=yellow>No callers found. This might be an entry point or dead code.</>');

            return Command::SUCCESS;
        }

        $this->line('Found ' . count($callers) . ' caller(s):');
        $this->newLine();

        foreach ($callers as $ref) {
            $confidence = $this->formatConfidence($ref->confidence);
            $type = $ref->callType->label();
            $this->line("  <fg=cyan>{$ref->callerFqn}</> {$confidence}");
            $this->line("    <fg=gray>[{$type}] {$ref->file}:{$ref->line}</>");
        }

        return Command::SUCCESS;
    }

    /**
     * Show what a method/class calls.
     */
    private function showCalls(UsageAnalyzer $analyzer, string $fqn): int
    {
        if (! $analyzer->hasData()) {
            $this->error('No usage data found. Run "php artisan codelens:usage" first.');

            return Command::FAILURE;
        }

        $calls = $analyzer->getCallsFrom($fqn);

        if ($this->option('json')) {
            $this->line(json_encode([
                'source' => $fqn,
                'calls' => array_map(fn ($r) => $r->toArray(), $calls),
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info("=== What does {$fqn} call? ===");
        $this->newLine();

        if (empty($calls)) {
            $this->line('<fg=gray>No outgoing calls found.</>');

            return Command::SUCCESS;
        }

        $this->line('Found ' . count($calls) . ' call(s):');
        $this->newLine();

        foreach ($calls as $ref) {
            $confidence = $this->formatConfidence($ref->confidence);
            $type = $ref->callType->label();
            $this->line("  <fg=cyan>{$ref->calleeFqn}</> {$confidence}");
            $this->line("    <fg=gray>[{$type}] line {$ref->line}</>");
        }

        return Command::SUCCESS;
    }

    /**
     * Show entry points.
     */
    private function showEntryPoints(UsageAnalyzer $analyzer): int
    {
        if (! $analyzer->hasData()) {
            $this->error('No usage data found. Run "php artisan codelens:usage" first.');

            return Command::FAILURE;
        }

        $entryPoints = $analyzer->getEntryPoints();

        if ($this->option('json')) {
            $this->line(json_encode(['entry_points' => $entryPoints], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info('=== Entry Points ===');
        $this->newLine();
        $this->line('Methods/classes that have outgoing calls but no incoming calls:');
        $this->newLine();

        if (empty($entryPoints)) {
            $this->line('<fg=gray>No entry points found.</>');

            return Command::SUCCESS;
        }

        foreach ($entryPoints as $fqn) {
            $this->line("  • {$fqn}");
        }

        $this->newLine();
        $this->line('<fg=gray>Total: ' . count($entryPoints) . ' entry point(s)</>');

        return Command::SUCCESS;
    }

    /**
     * Show usage statistics.
     */
    private function showStats(UsageAnalyzer $analyzer): int
    {
        if (! $analyzer->hasData()) {
            $this->error('No usage data found. Run "php artisan codelens:usage" first.');

            return Command::FAILURE;
        }

        $stats = $analyzer->getStats();

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info('=== Usage Statistics ===');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total References', $stats['total_references']],
                ['Unique Callers', $stats['unique_callers']],
                ['Unique Callees', $stats['unique_callees']],
                ['Entry Points', $stats['entry_points']],
            ],
        );

        $this->newLine();
        $this->info('By Call Type:');

        $typeRows = [];
        foreach ($stats['by_type'] as $type => $count) {
            if ($count > 0) {
                $typeRows[] = [$type, $count];
            }
        }
        $this->table(['Type', 'Count'], $typeRows);

        return Command::SUCCESS;
    }

    /**
     * Format confidence for display.
     */
    private function formatConfidence(float $confidence): string
    {
        $percent = (int) ($confidence * 100);

        if ($confidence >= 0.9) {
            return "<fg=green>[{$percent}%]</>";
        }

        if ($confidence >= 0.7) {
            return "<fg=yellow>[{$percent}%]</>";
        }

        return "<fg=red>[{$percent}%]</>";
    }
}
