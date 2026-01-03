<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Symfony\Command;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Heuristics\FlagLevel;
use CodeLens\Core\Heuristics\HeuristicConfig;
use CodeLens\Core\Heuristics\HeuristicEngine;
use CodeLens\Core\Metrics\MetricsAnalyzer;
use CodeLens\Core\Scanner\Scanner;
use CodeLens\Core\Storage\JsonStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command for heuristic analysis.
 */
#[AsCommand(
    name: 'codelens:analyze',
    description: 'Analyze codebase with heuristic rules',
)]
class AnalyzeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Path to scan (defaults to src/)')
            ->addOption('level', 'l', InputOption::VALUE_OPTIONAL, 'Show flags of this level or higher', 'all')
            ->addOption('rule', 'r', InputOption::VALUE_OPTIONAL, 'Only show results for a specific rule')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $basePath = getcwd() ?: '.';
        $configPath = $basePath . '/codelens.php';
        $config = file_exists($configPath)
            ? Configuration::fromFile($configPath)
            : new Configuration();

        $storage = new JsonStorage($basePath . '/.codelens');

        $scanner = new Scanner($config, $basePath, $storage);
        $metricsAnalyzer = new MetricsAnalyzer($config, $basePath);
        $heuristicEngine = new HeuristicEngine(new HeuristicConfig());

        // First, collect metrics
        $io->text('Collecting metrics...');
        $metricsResult = $metricsAnalyzer->analyze();

        // Then, apply heuristics
        $io->text('Applying heuristic rules...');
        $heuristicResult = $heuristicEngine->analyze($metricsResult);

        // Filter by level if specified
        $levelFilter = $input->getOption('level');
        $ruleFilter = $input->getOption('rule');

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
        if ($input->getOption('json')) {
            $output->writeln(json_encode([
                'flags' => array_map(fn ($f) => $f->toArray(), $flags),
                'summary' => $heuristicResult->getSummary(),
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        // Summary
        $summary = $heuristicResult->getSummary();
        $io->newLine();
        $io->title('Heuristic Analysis Results');

        $table = new Table($output);
        $table->setHeaders(['Level', 'Count']);
        $table->setRows([
            ['<fg=red>Review</>', $summary['by_level']['attention']],
            ['<fg=yellow>Note</>', $summary['by_level']['mild']],
            ['<fg=gray>Info</>', $summary['by_level']['neutral']],
        ]);
        $table->render();

        if (count($flags) === 0) {
            $io->success('No flags to display with current filters.');

            return Command::SUCCESS;
        }

        // Display flags grouped by level
        $this->displayFlagsByLevel($io, $flags, FlagLevel::Attention, 'red');
        $this->displayFlagsByLevel($io, $flags, FlagLevel::Mild, 'yellow');

        // Available rules
        $io->newLine();
        $io->section('Available Rules');
        foreach ($heuristicEngine->getRulesSummary() as $rule) {
            $status = $rule['enabled'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $io->text("  {$status} {$rule['id']}: {$rule['name']}");
        }

        return Command::SUCCESS;
    }

    private function displayFlagsByLevel(SymfonyStyle $io, array $flags, FlagLevel $level, string $color): void
    {
        $levelFlags = array_filter($flags, fn ($f) => $f->level === $level);

        if (count($levelFlags) === 0) {
            return;
        }

        $io->newLine();
        $io->section("<fg={$color}>{$level->label()} Level Flags</>");

        foreach ($levelFlags as $flag) {
            $io->text("<fg={$color}>[{$flag->ruleName}]</> {$flag->message}");
            $io->text("  <fg=gray>→ {$flag->reasoning}</>");
            $io->newLine();
        }
    }
}
