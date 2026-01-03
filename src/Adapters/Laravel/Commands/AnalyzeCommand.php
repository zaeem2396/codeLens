<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Laravel\Commands;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Heuristics\FlagLevel;
use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Heuristics\HeuristicEngine;
use CodeLens\Core\Metrics\MetricsAnalyzer;
use CodeLens\Core\Scanner\Scanner;
use CodeLens\Core\Storage\JsonStorage;
use Illuminate\Console\Command;

/**
 * Laravel Artisan command for heuristic analysis.
 */
class AnalyzeCommand extends Command
{
    protected $signature = 'codelens:analyze
        {--path= : Path to scan (defaults to app/)}
        {--level=all : Show flags of this level or higher (attention, mild, all)}
        {--rule= : Only show results for a specific rule}
        {--json : Output results as JSON}';

    protected $description = 'Analyze codebase with heuristic rules';

    public function handle(): int
    {
        $configPath = base_path('codelens.php');
        $config = file_exists($configPath)
            ? Configuration::fromFile($configPath)
            : new Configuration();

        $basePath = base_path();
        $storage = new JsonStorage($basePath . '/.codelens');

        $scanner = new Scanner($config, $basePath, $storage);
        $metricsAnalyzer = new MetricsAnalyzer($config, $basePath);
        $heuristicEngine = new HeuristicEngine(new HeuristicConfig());

        // First, collect metrics
        $this->info('Collecting metrics...');
        $metricsResult = $metricsAnalyzer->analyze();

        // Then, apply heuristics
        $this->info('Applying heuristic rules...');
        $heuristicResult = $heuristicEngine->analyze($metricsResult);

        // Filter by level if specified
        $levelFilter = $this->option('level');
        $ruleFilter = $this->option('rule');

        $flags = $heuristicResult->getFlags();

        // Filter by rule if specified
        if ($ruleFilter) {
            $flags = array_filter($flags, fn ($f) => $f->ruleId === $ruleFilter);
        }

        // Filter by level if specified
        if ($levelFilter === 'attention') {
            $flags = array_filter($flags, fn ($f) => $f->level === FlagLevel::Attention);
        } elseif ($levelFilter === 'mild') {
            $flags = array_filter(
                $flags,
                fn ($f) => $f->level === FlagLevel::Attention || $f->level === FlagLevel::Mild,
            );
        }

        $flags = array_values($flags);

        // JSON output
        if ($this->option('json')) {
            $this->line(json_encode([
                'flags' => array_map(fn ($f) => $f->toArray(), $flags),
                'summary' => $heuristicResult->getSummary(),
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        // Summary
        $summary = $heuristicResult->getSummary();
        $this->newLine();
        $this->info('=== Heuristic Analysis Results ===');
        $this->newLine();

        $this->table(
            ['Level', 'Count'],
            [
                ['<fg=red>Review</>', $summary['by_level']['attention']],
                ['<fg=yellow>Note</>', $summary['by_level']['mild']],
                ['<fg=gray>Info</>', $summary['by_level']['neutral']],
            ],
        );

        if (count($flags) === 0) {
            $this->info('No flags to display with current filters.');

            return Command::SUCCESS;
        }

        // Display flags grouped by level
        $this->newLine();
        $this->displayFlagsByLevel($flags, FlagLevel::Attention, '<fg=red>');
        $this->displayFlagsByLevel($flags, FlagLevel::Mild, '<fg=yellow>');

        // Available rules
        $this->newLine();
        $this->info('Available rules:');
        foreach ($heuristicEngine->getRulesSummary() as $rule) {
            $status = $rule['enabled'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  {$status} {$rule['id']}: {$rule['name']}");
        }

        return Command::SUCCESS;
    }

    private function displayFlagsByLevel(array $flags, FlagLevel $level, string $colorTag): void
    {
        $levelFlags = array_filter($flags, fn ($f) => $f->level === $level);

        if (count($levelFlags) === 0) {
            return;
        }

        $this->line("{$colorTag}=== {$level->label()} Level Flags ===</>");
        $this->newLine();

        foreach ($levelFlags as $flag) {
            $this->line("{$colorTag}[{$flag->ruleName}]</> {$flag->message}");
            $this->line("  <fg=gray>→ {$flag->reasoning}</>");
            $this->newLine();
        }
    }
}
