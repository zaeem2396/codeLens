<?php

declare(strict_types=1);

namespace CodeLens\Adapters\Laravel\Commands;

use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Scanner\Scanner;
use CodeLens\Core\Storage\StorageFactory;
use Illuminate\Console\Command;

/**
 * Artisan command to scan the codebase.
 */
class ScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'codelens:scan 
                            {--fresh : Clear cache and perform a fresh scan}
                            {--path= : Scan a specific path only}';

    /**
     * The console command description.
     */
    protected $description = 'Scan the codebase and build the symbol index';

    /**
     * Execute the console command.
     */
    public function handle(Configuration $config): int
    {
        $this->info('');
        $this->info('🔍 CodeLens Scanner');
        $this->info('==================');
        $this->info('');

        $fresh = (bool) $this->option('fresh');
        $path = $this->option('path');

        if ($fresh) {
            $this->warn('Performing fresh scan (clearing existing cache)...');
        }

        $basePath = base_path();
        $storagePath = storage_path('framework');
        
        $storage = StorageFactory::create($config, $storagePath);

        if ($fresh) {
            $storage->clear();
        }

        $scanner = new Scanner($config, $basePath, $storage);

        // Set up progress callback
        $scanner->onProgress(function (string $file, int $current, int $total) {
            $this->output->write("\r  Scanning [{$current}/{$total}] {$file}" . str_repeat(' ', 20));
        });

        $this->info('  Starting scan...');
        $this->info('');

        // Run scan
        if ($path !== null) {
            $this->info("  Scanning path: {$path}");
            $result = $scanner->scanPath($path);
        } else {
            $result = $scanner->scan($fresh);
        }

        // Clear progress line
        $this->output->write("\r" . str_repeat(' ', 80) . "\r");

        // Display results
        $this->displayResults($result);

        return $result->isSuccess() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Display scan results.
     */
    private function displayResults($result): void
    {
        $this->info('');
        $this->info('📊 Scan Results');
        $this->info('---------------');
        $this->info('');

        // File statistics
        $this->info('  Files:');
        $this->line("    • Scanned:   {$result->scannedFiles}");
        $this->line("    • Unchanged: {$result->unchangedFiles}");
        $this->line("    • Removed:   {$result->removedFiles}");
        $this->line("    • Total:     {$result->totalFiles}");
        $this->info('');

        // Symbol statistics
        $stats = $result->getSymbolStats();
        $this->info('  Symbols:');
        $this->line("    • Classes:    {$stats['classes']}");
        $this->line("    • Interfaces: {$stats['interfaces']}");
        $this->line("    • Traits:     {$stats['traits']}");
        $this->line("    • Enums:      {$stats['enums']}");
        $this->line("    • Total:      {$result->totalSymbols}");
        $this->info('');

        // Duration
        $this->info("  Duration: {$result->getFormattedDuration()}");
        $this->info('');

        // Errors
        if ($result->hasErrors()) {
            $this->warn("  ⚠️  {$result->getErrorCount()} file(s) had parse errors:");
            $this->info('');
            
            foreach (array_slice($result->errors, 0, 10) as $file => $error) {
                $shortPath = str_replace(base_path() . '/', '', $file);
                $this->line("    • {$shortPath}");
                $this->line("      {$error}");
            }

            if (count($result->errors) > 10) {
                $remaining = count($result->errors) - 10;
                $this->line("    ... and {$remaining} more");
            }
            $this->info('');
        }

        // Success message
        if ($result->isSuccess()) {
            $this->info('  ✅ Scan completed successfully!');
        } else {
            $this->warn('  ⚠️  Scan completed with errors');
        }
        $this->info('');
    }
}

