<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Symfony\Command;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Metrics\MetricsAnalyzer;
use CodeLens\Core\Metrics\MetricsResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Symfony console command to display codebase metrics.
 */
#[AsCommand(
    name: 'codelens:metrics',
    description: 'Display codebase metrics (lines of code, classes, methods, etc.)',
)]
class MetricsCommand extends Command
{
    private Configuration $config;
    private KernelInterface $kernel;

    public function __construct(Configuration $config, KernelInterface $kernel)
    {
        parent::__construct();
        $this->config = $config;
        $this->kernel = $kernel;
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Analyze a specific path only')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of files to show in the table', '10')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Show all files in the table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getOption('path');
        $json = (bool) $input->getOption('json');

        $basePath = $this->kernel->getProjectDir();

        $analyzer = new MetricsAnalyzer($this->config, $basePath);

        if (! $json) {
            $io->title('📊 CodeLens Metrics');

            $analyzer->onProgress(function (string $file, int $current, int $total) use ($output): void {
                $output->write("\r  Analyzing [{$current}/{$total}] {$file}" . str_repeat(' ', 20));
            });
        }

        // Run analysis
        if ($path !== null) {
            $result = $analyzer->analyzePath($path);
        } else {
            $result = $analyzer->analyze();
        }

        if ($json) {
            $output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        // Clear progress line
        $output->write("\r" . str_repeat(' ', 80) . "\r");

        // Determine limit for file display
        $showAll = (bool) $input->getOption('all');
        $limit = $showAll ? null : (int) $input->getOption('limit');

        // Display results
        $this->displayResults($io, $output, $result, $limit);

        return Command::SUCCESS;
    }

    /**
     * Display metrics results.
     */
    private function displayResults(SymfonyStyle $io, OutputInterface $output, MetricsResult $result, ?int $limit): void
    {
        $summary = $result->getSummary();

        $io->section('📈 Summary');

        $io->listing([
            "Files analyzed:      {$summary['file_count']}",
            "Total lines:         {$summary['total_lines_of_code']}",
            "Lines (no comments): {$summary['total_lines_without_comments']}",
        ]);

        $io->text('Symbols:');
        $io->listing([
            "Classes:    {$summary['total_classes']}",
            "Interfaces: {$summary['total_interfaces']}",
            "Traits:     {$summary['total_traits']}",
            "Enums:      {$summary['total_enums']}",
            "Methods:    {$summary['total_methods']}",
        ]);

        $io->text('Methods:');
        $io->listing([
            "Avg length:  {$summary['average_method_length']} lines",
            "Max nesting: {$summary['max_nesting_depth']} levels",
        ]);

        $io->text("Duration: {$summary['duration']}");
        $io->newLine();

        // Top files by lines
        $this->displayTopFiles($io, $output, $result, $limit);
    }

    /**
     * Display top files by lines of code.
     *
     * @param int|null $limit Number of files to show (null = all)
     */
    private function displayTopFiles(SymfonyStyle $io, OutputInterface $output, MetricsResult $result, ?int $limit): void
    {
        $files = $result->fileMetrics;

        if (count($files) === 0) {
            return;
        }

        $totalFiles = count($files);
        $displayCount = $limit ?? $totalFiles;
        $label = $limit === null ? "All {$totalFiles}" : "Top {$displayCount}";

        $io->section("📄 {$label} Files by Lines of Code");

        // Sort by lines of code
        usort($files, fn ($a, $b) => $b->linesOfCode <=> $a->linesOfCode);

        // Apply limit
        $filesToShow = $limit === null ? $files : array_slice($files, 0, $limit);

        $table = new Table($output);
        $table->setHeaders(['File', 'LOC', 'Classes', 'Methods', 'Max Nest']);

        foreach ($filesToShow as $file) {
            $table->addRow([
                $this->truncatePath($file->relativePath, 50),
                $file->linesOfCode,
                $file->classCount,
                $file->methodCount,
                $file->getMaxNestingDepth(),
            ]);
        }

        $table->render();
        $io->newLine();
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
