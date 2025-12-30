<?php

declare(strict_types=1);

namespace CodeLens\Core\Storage;

use CodeLens\Core\Index\FileIndex;
use CodeLens\Core\Index\SymbolRegistry;
use CodeLens\Core\Index\Symbols\ClassSymbol;
use CodeLens\Core\Index\Symbols\EnumSymbol;
use CodeLens\Core\Index\Symbols\InterfaceSymbol;
use CodeLens\Core\Index\Symbols\TraitSymbol;
use CodeLens\Core\Scanner\FileInfo;
use PDO;

/**
 * SQLite-based storage implementation.
 * 
 * Provides faster lookups for large codebases.
 */
final class SqliteStorage implements StorageInterface
{
    private string $storagePath;
    private ?PDO $pdo = null;

    public function __construct(string $storagePath)
    {
        $this->storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);
    }

    /**
     * Save the file index.
     */
    public function saveFileIndex(FileIndex $index): void
    {
        $pdo = $this->getConnection();
        
        // Clear existing data
        $pdo->exec('DELETE FROM files');

        $stmt = $pdo->prepare('
            INSERT INTO files (path, relative_path, size, last_modified, checksum, line_count)
            VALUES (:path, :relative_path, :size, :last_modified, :checksum, :line_count)
        ');

        foreach ($index->all() as $file) {
            $stmt->execute([
                'path' => $file->absolutePath,
                'relative_path' => $file->relativePath,
                'size' => $file->size,
                'last_modified' => $file->lastModified,
                'checksum' => $file->checksum,
                'line_count' => $file->lineCount,
            ]);
        }
    }

    /**
     * Load the file index.
     */
    public function loadFileIndex(): FileIndex
    {
        $pdo = $this->getConnection();
        $index = new FileIndex();

        $stmt = $pdo->query('SELECT * FROM files');
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $index->add(new FileInfo(
                absolutePath: $row['path'],
                relativePath: $row['relative_path'],
                size: (int) $row['size'],
                lastModified: (int) $row['last_modified'],
                checksum: $row['checksum'],
                lineCount: $row['line_count'] !== null ? (int) $row['line_count'] : null
            ));
        }

        return $index;
    }

    /**
     * Save the symbol registry.
     */
    public function saveSymbolRegistry(SymbolRegistry $registry): void
    {
        $pdo = $this->getConnection();
        
        // Clear existing data
        $pdo->exec('DELETE FROM symbols');

        $stmt = $pdo->prepare('
            INSERT INTO symbols (fqn, type, name, file, line_start, line_end, namespace, data)
            VALUES (:fqn, :type, :name, :file, :line_start, :line_end, :namespace, :data)
        ');

        foreach ($registry->all() as $symbol) {
            $data = $symbol->toArray();
            $namespace = null;
            
            if ($symbol instanceof ClassSymbol ||
                $symbol instanceof InterfaceSymbol ||
                $symbol instanceof TraitSymbol ||
                $symbol instanceof EnumSymbol) {
                $namespace = $symbol->namespace;
            }

            $stmt->execute([
                'fqn' => $symbol->getFqn(),
                'type' => $symbol->getType(),
                'name' => $symbol->getName(),
                'file' => $symbol->getFile(),
                'line_start' => $symbol->getLineStart(),
                'line_end' => $symbol->getLineEnd(),
                'namespace' => $namespace,
                'data' => json_encode($data),
            ]);
        }
    }

    /**
     * Load the symbol registry.
     */
    public function loadSymbolRegistry(): SymbolRegistry
    {
        $pdo = $this->getConnection();
        $registry = new SymbolRegistry();

        $stmt = $pdo->query('SELECT * FROM symbols');
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data = json_decode($row['data'], true);
            $symbol = $this->createSymbolFromArray($data);
            
            if ($symbol !== null) {
                $registry->register($symbol);
            }
        }

        return $registry;
    }

    /**
     * Save scan metadata.
     */
    public function saveScanMetadata(array $metadata): void
    {
        $pdo = $this->getConnection();
        
        $pdo->exec('DELETE FROM metadata');
        
        $stmt = $pdo->prepare('INSERT INTO metadata (key, value) VALUES (:key, :value)');
        
        foreach ($metadata as $key => $value) {
            $stmt->execute([
                'key' => $key,
                'value' => is_array($value) ? json_encode($value) : (string) $value,
            ]);
        }
    }

    /**
     * Load scan metadata.
     */
    public function loadScanMetadata(): array
    {
        $pdo = $this->getConnection();
        $metadata = [];

        $stmt = $pdo->query('SELECT key, value FROM metadata');
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['value'];
            $decoded = json_decode($value, true);
            $metadata[$row['key']] = $decoded !== null ? $decoded : $value;
        }

        return $metadata;
    }

    /**
     * Check if storage has data.
     */
    public function hasData(): bool
    {
        if (!file_exists($this->getDatabasePath())) {
            return false;
        }

        $pdo = $this->getConnection();
        $stmt = $pdo->query('SELECT COUNT(*) FROM files');
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Clear all stored data.
     */
    public function clear(): void
    {
        $dbPath = $this->getDatabasePath();
        
        if (file_exists($dbPath)) {
            $this->pdo = null;
            @unlink($dbPath);
        }
    }

    /**
     * Get storage path.
     */
    public function getPath(): string
    {
        return $this->storagePath;
    }

    /**
     * Get the database path.
     */
    private function getDatabasePath(): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . 'codelens.db';
    }

    /**
     * Get PDO connection.
     */
    private function getConnection(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $this->ensureDirectory();
        
        $this->pdo = new PDO('sqlite:' . $this->getDatabasePath());
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->initializeSchema();

        return $this->pdo;
    }

    /**
     * Ensure storage directory exists.
     */
    private function ensureDirectory(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Initialize database schema.
     */
    private function initializeSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS files (
                path TEXT PRIMARY KEY,
                relative_path TEXT NOT NULL,
                size INTEGER NOT NULL,
                last_modified INTEGER NOT NULL,
                checksum TEXT,
                line_count INTEGER
            )
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS symbols (
                fqn TEXT PRIMARY KEY,
                type TEXT NOT NULL,
                name TEXT NOT NULL,
                file TEXT NOT NULL,
                line_start INTEGER NOT NULL,
                line_end INTEGER NOT NULL,
                namespace TEXT,
                data TEXT NOT NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS metadata (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )
        ');

        // Create indexes for common queries
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_symbols_file ON symbols(file)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_symbols_type ON symbols(type)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_symbols_namespace ON symbols(namespace)');
    }

    /**
     * Create a symbol from array data.
     */
    private function createSymbolFromArray(array $data): ?object
    {
        $type = $data['type'] ?? null;

        return match ($type) {
            'class' => ClassSymbol::fromArray($data),
            'interface' => InterfaceSymbol::fromArray($data),
            'trait' => TraitSymbol::fromArray($data),
            'enum' => EnumSymbol::fromArray($data),
            default => null,
        };
    }
}

