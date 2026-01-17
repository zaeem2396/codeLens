<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Laravel\Commands;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Metrics\MetricsAnalyzer;
use CodeLens\Core\Metrics\MetricsResult;
use Illuminate\Console\Command;

/**
 * Artisan command to display codebase metrics.
 */
class MetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'codelens:metrics
                            {--path= : Analyze a specific path only}
                            {--json : Output as JSON}
                            {--limit=10 : Number of files to show in the table (default: 10)}
                            {--all : Show all files in the table}';

    /**
     * The console command description.
     */
    protected $description = 'Display codebase metrics (lines of code, classes, methods, etc.)';

    /**
     * Execute the console command.
     */
    public function handle(Configuration $config): int
    {
        $path = $this->option('path');
        $json = (bool) $this->option('json');

        $basePath = base_path();

        $analyzer = new MetricsAnalyzer($config, $basePath);

        if (! $json) {
            $this->info('');
            $this->info('📊 CodeLens Metrics');
            $this->info('==================');
            $this->info('');

            $analyzer->onProgress(function (string $file, int $current, int $total): void {
                $this->output->write("\r  Analyzing [{$current}/{$total}] {$file}" . str_repeat(' ', 20));
            });
        }

        // Run analysis
        if ($path !== null) {
            $result = $analyzer->analyzePath($path);
        } else {
            $result = $analyzer->analyze();
        }

        if ($json) {
            $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        // Clear progress line
        $this->output->write("\r" . str_repeat(' ', 80) . "\r");

        // Determine limit for file display
        $showAll = (bool) $this->option('all');
        $limit = $showAll ? null : (int) $this->option('limit');

        // Display results
        $this->displayResults($result, $limit);

        return Command::SUCCESS;
    }

    /**
     * Display metrics results.
     */
    private function displayResults(MetricsResult $result, ?int $limit): void
    {
        $summary = $result->getSummary();

        $this->info('');
        $this->info('📈 Summary');
        $this->info('----------');
        $this->info('');

        // File stats
        $this->line("  Files analyzed:     {$summary['file_count']}");
        $this->line("  Total lines:        {$summary['total_lines_of_code']}");
        $this->line("  Lines (no comments):{$summary['total_lines_without_comments']}");
        $this->info('');

        // Symbol stats
        $this->info('  Symbols:');
        $this->line("    Classes:          {$summary['total_classes']}");
        $this->line("    Interfaces:       {$summary['total_interfaces']}");
        $this->line("    Traits:           {$summary['total_traits']}");
        $this->line("    Enums:            {$summary['total_enums']}");
        $this->line("    Methods:          {$summary['total_methods']}");
        $this->info('');

        // Method stats
        $this->info('  Methods:');
        $this->line("    Avg length:       {$summary['average_method_length']} lines");
        $this->line("    Max nesting:      {$summary['max_nesting_depth']} levels");
        $this->info('');

        // Duration
        $this->info("  Duration: {$summary['duration']}");
        $this->info('');

        // Top files by lines
        $this->displayTopFiles($result, $limit);
    }

    /**
     * Display top files by lines of code.
     *
     * @param MetricsResult $result The metrics result
     * @param int|null $limit Number of files to show (null = all)
     */
    private function displayTopFiles(MetricsResult $result, ?int $limit): void
    {
        $files = $result->fileMetrics;

        if (count($files) === 0) {
            return;
        }

        $totalFiles = count($files);
        $displayCount = $limit ?? $totalFiles;
        $label = $limit === null ? "All {$totalFiles}" : "Top {$displayCount}";

        $this->info("📄 {$label} Files by Lines of Code");
        $this->info('-------------------------');
        $this->info('');

        // Sort by lines of code
        usort($files, fn ($a, $b) => $b->linesOfCode <=> $a->linesOfCode);

        // Apply limit
        $filesToShow = $limit === null ? $files : array_slice($files, 0, $limit);

        $headers = ['File', 'LOC', 'Classes', 'Methods', 'Max Nest'];
        $rows = [];

        foreach ($filesToShow as $file) {
            $rows[] = [
                $this->truncatePath($file->relativePath, 50),
                $file->linesOfCode,
                $file->classCount,
                $file->methodCount,
                $file->getMaxNestingDepth(),
            ];
        }

        $this->table($headers, $rows);
        $this->info('');
    }

    /**
     * Truncate a path for display.
     */
    private function truncatePath(string $path, int $maxLength): string
    {
        if (strlen($path) <= $maxLength) {
            return $path;
        }

        return '...' . substr($path, -($maxLength - 3));
    }
}
