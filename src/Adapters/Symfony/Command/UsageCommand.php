<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Symfony\Command;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Storage\JsonStorage;
use CodeLens\Core\Usage\CallType;
use CodeLens\Core\Usage\Storage\UsageStorage;
use CodeLens\Core\Usage\UsageAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command for usage analysis.
 */
#[AsCommand(
    name: 'codelens:usage',
    description: 'Analyze code usage and dependencies',
)]
class UsageCommand extends Command
{
    private Configuration $config;
    private string $projectDir;

    public function __construct(Configuration $config, string $projectDir)
    {
        parent::__construct();
        $this->config = $config;
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Path to analyze')
            ->addOption('show', 's', InputOption::VALUE_OPTIONAL, 'Show usage for a specific class')
            ->addOption('callers', null, InputOption::VALUE_OPTIONAL, 'Show who calls a specific method/class')
            ->addOption('calls', null, InputOption::VALUE_OPTIONAL, 'Show what a specific method/class calls')
            ->addOption('entry-points', null, InputOption::VALUE_NONE, 'Show potential entry points')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show usage statistics only')
            ->addOption('fresh', 'f', InputOption::VALUE_NONE, 'Force fresh analysis')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $storagePath = $this->projectDir . '/.codelens';

        // Load symbol registry
        $jsonStorage = new JsonStorage($storagePath);

        if (! $jsonStorage->hasData()) {
            $io->error('No scan data found. Run "bin/console codelens:scan" first.');

            return Command::FAILURE;
        }

        $symbolRegistry = $jsonStorage->loadSymbolRegistry();
        $usageStorage = new UsageStorage($storagePath);

        $analyzer = new UsageAnalyzer($this->config, $this->projectDir, $symbolRegistry, $usageStorage);

        // Handle --show option
        if ($fqn = $input->getOption('show')) {
            return $this->showClassUsage($io, $input, $analyzer, $fqn);
        }

        // Handle --callers option
        if ($fqn = $input->getOption('callers')) {
            return $this->showCallers($io, $input, $analyzer, $fqn);
        }

        // Handle --calls option
        if ($fqn = $input->getOption('calls')) {
            return $this->showCalls($io, $input, $analyzer, $fqn);
        }

        // Handle --entry-points option
        if ($input->getOption('entry-points')) {
            return $this->showEntryPoints($io, $input, $analyzer);
        }

        // Handle --stats option
        if ($input->getOption('stats')) {
            return $this->showStats($io, $input, $analyzer);
        }

        // Run full analysis
        return $this->runAnalysis($io, $input, $output, $analyzer);
    }

    /**
     * Run full usage analysis.
     */
    private function runAnalysis(
        SymfonyStyle $io,
        InputInterface $input,
        OutputInterface $output,
        UsageAnalyzer $analyzer,
    ): int {
        $fresh = $input->getOption('fresh');

        if (! $fresh && $analyzer->hasData()) {
            $io->info('Using cached analysis data. Use --fresh to re-analyze.');

            return $this->showStats($io, $input, $analyzer);
        }

        $io->title('CodeLens Usage Analysis');

        $progressBar = new ProgressBar($output);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        $analyzer->onProgress(function (string $file, int $current, int $total) use ($progressBar) {
            $progressBar->setMaxSteps($total);
            $progressBar->setProgress($current);
            $progressBar->setMessage($file);
        });

        $result = $analyzer->analyze();

        $progressBar->finish();
        $io->newLine(2);

        // JSON output
        if ($input->getOption('json')) {
            $output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        // Display summary
        $summary = $result->getSummary();

        $io->success('Usage Analysis Complete');

        $io->table(
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
        $io->section('References by Type');

        $typeRows = [];
        foreach ($summary['by_type'] as $type => $count) {
            if ($count > 0) {
                $typeRows[] = [CallType::from($type)->label(), $count];
            }
        }
        $io->table(['Type', 'Count'], $typeRows);

        if ($result->hasErrors()) {
            $io->warning("Encountered {$result->getErrorCount()} errors during analysis.");
        }

        return Command::SUCCESS;
    }

    /**
     * Show usage for a specific class.
     */
    private function showClassUsage(
        SymfonyStyle $io,
        InputInterface $input,
        UsageAnalyzer $analyzer,
        string $fqn,
    ): int {
        if (! $analyzer->hasData()) {
            $io->error('No usage data found. Run "bin/console codelens:usage" first.');

            return Command::FAILURE;
        }

        $usage = $analyzer->getClassUsage($fqn);

        if ($input->getOption('json')) {
            $io->writeln(json_encode([
                'class' => $fqn,
                'incoming' => array_map(fn ($r) => $r->toArray(), $usage['incoming']),
                'outgoing' => array_map(fn ($r) => $r->toArray(), $usage['outgoing']),
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->title("Usage for {$fqn}");

        // Incoming calls
        $io->section('Incoming Calls (' . count($usage['incoming']) . ')');
        if (empty($usage['incoming'])) {
            $io->text('No incoming calls found');
        } else {
            foreach ($usage['incoming'] as $ref) {
                $confidence = $this->formatConfidence($ref->confidence);
                $io->text("  ← {$ref->callerFqn} {$confidence}");
                $io->text("    {$ref->file}:{$ref->line}");
            }
        }

        // Outgoing calls
        $io->section('Outgoing Calls (' . count($usage['outgoing']) . ')');
        if (empty($usage['outgoing'])) {
            $io->text('No outgoing calls found');
        } else {
            foreach ($usage['outgoing'] as $ref) {
                $confidence = $this->formatConfidence($ref->confidence);
                $io->text("  → {$ref->calleeFqn} {$confidence}");
                $io->text("    {$ref->file}:{$ref->line}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Show callers of a method/class.
     */
    private function showCallers(
        SymfonyStyle $io,
        InputInterface $input,
        UsageAnalyzer $analyzer,
        string $fqn,
    ): int {
        if (! $analyzer->hasData()) {
            $io->error('No usage data found. Run "bin/console codelens:usage" first.');

            return Command::FAILURE;
        }

        $callers = $analyzer->getCallersOf($fqn);

        if ($input->getOption('json')) {
            $io->writeln(json_encode([
                'target' => $fqn,
                'callers' => array_map(fn ($r) => $r->toArray(), $callers),
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->title("Who calls {$fqn}?");

        if (empty($callers)) {
            $io->warning('No callers found. This might be an entry point or dead code.');

            return Command::SUCCESS;
        }

        $io->text('Found ' . count($callers) . ' caller(s):');
        $io->newLine();

        foreach ($callers as $ref) {
            $confidence = $this->formatConfidence($ref->confidence);
            $type = $ref->callType->label();
            $io->text("  {$ref->callerFqn} {$confidence}");
            $io->text("    [{$type}] {$ref->file}:{$ref->line}");
        }

        return Command::SUCCESS;
    }

    /**
     * Show what a method/class calls.
     */
    private function showCalls(
        SymfonyStyle $io,
        InputInterface $input,
        UsageAnalyzer $analyzer,
        string $fqn,
    ): int {
        if (! $analyzer->hasData()) {
            $io->error('No usage data found. Run "bin/console codelens:usage" first.');

            return Command::FAILURE;
        }

        $calls = $analyzer->getCallsFrom($fqn);

        if ($input->getOption('json')) {
            $io->writeln(json_encode([
                'source' => $fqn,
                'calls' => array_map(fn ($r) => $r->toArray(), $calls),
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->title("What does {$fqn} call?");

        if (empty($calls)) {
            $io->text('No outgoing calls found.');

            return Command::SUCCESS;
        }

        $io->text('Found ' . count($calls) . ' call(s):');
        $io->newLine();

        foreach ($calls as $ref) {
            $confidence = $this->formatConfidence($ref->confidence);
            $type = $ref->callType->label();
            $io->text("  {$ref->calleeFqn} {$confidence}");
            $io->text("    [{$type}] line {$ref->line}");
        }

        return Command::SUCCESS;
    }

    /**
     * Show entry points.
     */
    private function showEntryPoints(
        SymfonyStyle $io,
        InputInterface $input,
        UsageAnalyzer $analyzer,
    ): int {
        if (! $analyzer->hasData()) {
            $io->error('No usage data found. Run "bin/console codelens:usage" first.');

            return Command::FAILURE;
        }

        $entryPoints = $analyzer->getEntryPoints();

        if ($input->getOption('json')) {
            $io->writeln(json_encode(['entry_points' => $entryPoints], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->title('Entry Points');
        $io->text('Methods/classes that have outgoing calls but no incoming calls:');
        $io->newLine();

        if (empty($entryPoints)) {
            $io->text('No entry points found.');

            return Command::SUCCESS;
        }

        foreach ($entryPoints as $fqn) {
            $io->text("  • {$fqn}");
        }

        $io->newLine();
        $io->text('Total: ' . count($entryPoints) . ' entry point(s)');

        return Command::SUCCESS;
    }

    /**
     * Show usage statistics.
     */
    private function showStats(
        SymfonyStyle $io,
        InputInterface $input,
        UsageAnalyzer $analyzer,
    ): int {
        if (! $analyzer->hasData()) {
            $io->error('No usage data found. Run "bin/console codelens:usage" first.');

            return Command::FAILURE;
        }

        $stats = $analyzer->getStats();

        if ($input->getOption('json')) {
            $io->writeln(json_encode($stats, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->title('Usage Statistics');

        $io->table(
            ['Metric', 'Value'],
            [
                ['Total References', $stats['total_references']],
                ['Unique Callers', $stats['unique_callers']],
                ['Unique Callees', $stats['unique_callees']],
                ['Entry Points', $stats['entry_points']],
            ],
        );

        $io->section('By Call Type');

        $typeRows = [];
        foreach ($stats['by_type'] as $type => $count) {
            if ($count > 0) {
                $typeRows[] = [$type, $count];
            }
        }
        $io->table(['Type', 'Count'], $typeRows);

        return Command::SUCCESS;
    }

    /**
     * Format confidence for display.
     */
    private function formatConfidence(float $confidence): string
    {
        $percent = (int) ($confidence * 100);

        return "[{$percent}%]";
    }
}
