<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Symfony\Command;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Scanner\Scanner;
use CodeLens\Core\Scanner\ScanResult;
use CodeLens\Core\Storage\StorageFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Symfony console command to scan the codebase.
 */
#[AsCommand(
    name: 'codelens:scan',
    description: 'Scan the codebase and build the symbol index',
)]
class ScanCommand extends Command
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
            ->addOption('fresh', 'f', InputOption::VALUE_NONE, 'Clear cache and perform a fresh scan')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Scan a specific path only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔍 CodeLens Scanner');

        $fresh = (bool) $input->getOption('fresh');
        $path = $input->getOption('path');

        if ($fresh) {
            $io->warning('Performing fresh scan (clearing existing cache)...');
        }

        $basePath = $this->kernel->getProjectDir();
        $storagePath = $this->kernel->getCacheDir();

        $storage = StorageFactory::create($this->config, $storagePath);

        if ($fresh) {
            $storage->clear();
        }

        $scanner = new Scanner($this->config, $basePath, $storage);

        // Set up progress callback
        $currentFile = '';
        $scanner->onProgress(function (string $file, int $current, int $total) use ($output, &$currentFile) {
            $currentFile = $file;
            $output->write("\r  Scanning [{$current}/{$total}] {$file}" . str_repeat(' ', 20));
        });

        $io->text('Starting scan...');
        $io->newLine();

        // Run scan
        if ($path !== null) {
            $io->text("Scanning path: {$path}");
            $result = $scanner->scanPath($path);
        } else {
            $result = $scanner->scan($fresh);
        }

        // Clear progress line
        $output->write("\r" . str_repeat(' ', 80) . "\r");

        // Display results
        $this->displayResults($io, $result);

        return $result->isSuccess() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Display scan results.
     */
    private function displayResults(SymfonyStyle $io, ScanResult $result): void
    {
        $io->section('📊 Scan Results');

        // File statistics
        $io->text('Files:');
        $io->listing([
            "Scanned:   {$result->scannedFiles}",
            "Unchanged: {$result->unchangedFiles}",
            "Removed:   {$result->removedFiles}",
            "Total:     {$result->totalFiles}",
        ]);

        // Symbol statistics
        $stats = $result->getSymbolStats();
        $io->text('Symbols:');
        $io->listing([
            "Classes:    {$stats['classes']}",
            "Interfaces: {$stats['interfaces']}",
            "Traits:     {$stats['traits']}",
            "Enums:      {$stats['enums']}",
            "Total:      {$result->totalSymbols}",
        ]);

        // Duration
        $io->text("Duration: {$result->getFormattedDuration()}");
        $io->newLine();

        // Errors
        if ($result->hasErrors()) {
            $io->warning("{$result->getErrorCount()} file(s) had parse errors:");

            $errors = array_slice($result->errors, 0, 10);
            foreach ($errors as $file => $error) {
                $shortPath = str_replace($this->kernel->getProjectDir() . '/', '', $file);
                $io->text("  • {$shortPath}");
                $io->text("    {$error}");
            }

            if (count($result->errors) > 10) {
                $remaining = count($result->errors) - 10;
                $io->text("  ... and {$remaining} more");
            }
            $io->newLine();
        }

        // Success message
        if ($result->isSuccess()) {
            $io->success('Scan completed successfully!');
        } else {
            $io->warning('Scan completed with errors');
        }
    }
}
