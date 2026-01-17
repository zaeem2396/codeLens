<?php

declare(strict_types=1);

namespace CodeLens\Core\Usage\Storage;

use CodeLens\Core\Usage\CallGraph;
use CodeLens\Core\Usage\CallReference;
use CodeLens\Core\Usage\CallType;
use PDO;
use Throwable;

/**
 * SQLite-based storage for call graph data.
 *
 * Stores call references and usage summaries in a separate database.
 */
final class UsageStorage
{
    private string $storagePath;
    private ?PDO $pdo = null;

    public function __construct(string $storagePath)
    {
        $this->storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);
    }

    /**
     * Save the call graph.
     */
    public function saveCallGraph(CallGraph $graph): void
    {
        $pdo = $this->getConnection();

        // Use transaction for better performance
        $pdo->beginTransaction();

        try {
            // Clear existing data
            $pdo->exec('DELETE FROM call_references');
            $pdo->exec('DELETE FROM usage_summary');

            // Insert references
            $stmt = $pdo->prepare('
                INSERT INTO call_references 
                (caller_fqn, callee_fqn, call_type, file, line, confidence, context)
                VALUES (:caller_fqn, :callee_fqn, :call_type, :file, :line, :confidence, :context)
            ');

            foreach ($graph->getAllReferences() as $reference) {
                $stmt->execute([
                    'caller_fqn' => $reference->callerFqn,
                    'callee_fqn' => $reference->calleeFqn,
                    'call_type' => $reference->callType->value,
                    'file' => $reference->file,
                    'line' => $reference->line,
                    'confidence' => $reference->confidence,
                    'context' => json_encode($reference->context),
                ]);
            }

            // Build and save summaries
            $this->saveSummaries($graph, $pdo);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Load the call graph.
     */
    public function loadCallGraph(): CallGraph
    {
        $pdo = $this->getConnection();
        $graph = new CallGraph();

        $stmt = $pdo->query('SELECT * FROM call_references');

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $context = json_decode($row['context'], true) ?? [];

            $reference = new CallReference(
                callerFqn: $row['caller_fqn'],
                calleeFqn: $row['callee_fqn'],
                callType: CallType::from($row['call_type']),
                file: $row['file'],
                line: (int) $row['line'],
                confidence: (float) $row['confidence'],
                context: $context,
            );

            $graph->addReference($reference);
        }

        return $graph;
    }

    /**
     * Get usage summary for a FQN.
     */
    public function getSummary(string $fqn): ?array
    {
        $pdo = $this->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM usage_summary WHERE fqn = :fqn');
        $stmt->execute(['fqn' => $fqn]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return [
            'fqn' => $row['fqn'],
            'incoming_count' => (int) $row['incoming_count'],
            'outgoing_count' => (int) $row['outgoing_count'],
            'max_depth' => (int) $row['max_depth'],
            'is_entry_point' => (bool) $row['is_entry_point'],
        ];
    }

    /**
     * Get all summaries.
     *
     * @return array<string, array>
     */
    public function getAllSummaries(): array
    {
        $pdo = $this->getConnection();
        $summaries = [];

        $stmt = $pdo->query('SELECT * FROM usage_summary ORDER BY incoming_count DESC');

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $summaries[$row['fqn']] = [
                'fqn' => $row['fqn'],
                'incoming_count' => (int) $row['incoming_count'],
                'outgoing_count' => (int) $row['outgoing_count'],
                'max_depth' => (int) $row['max_depth'],
                'is_entry_point' => (bool) $row['is_entry_point'],
            ];
        }

        return $summaries;
    }

    /**
     * Get callers of a specific FQN.
     *
     * @return array<CallReference>
     */
    public function getCallersOf(string $fqn): array
    {
        $pdo = $this->getConnection();
        $references = [];

        $stmt = $pdo->prepare('SELECT * FROM call_references WHERE callee_fqn = :fqn');
        $stmt->execute(['fqn' => $fqn]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $references[] = $this->rowToReference($row);
        }

        return $references;
    }

    /**
     * Get calls from a specific FQN.
     *
     * @return array<CallReference>
     */
    public function getCallsFrom(string $fqn): array
    {
        $pdo = $this->getConnection();
        $references = [];

        $stmt = $pdo->prepare('SELECT * FROM call_references WHERE caller_fqn = :fqn');
        $stmt->execute(['fqn' => $fqn]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $references[] = $this->rowToReference($row);
        }

        return $references;
    }

    /**
     * Get all references for a class (all methods).
     *
     * @return array<CallReference>
     */
    public function getClassReferences(string $classFqn): array
    {
        $pdo = $this->getConnection();
        $references = [];

        // Incoming calls to any method of the class
        $stmt = $pdo->prepare('
            SELECT * FROM call_references 
            WHERE callee_fqn LIKE :pattern OR callee_fqn = :exact
        ');
        $stmt->execute([
            'pattern' => $classFqn . '::%',
            'exact' => $classFqn,
        ]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $references[] = $this->rowToReference($row);
        }

        return $references;
    }

    /**
     * Get entry points (methods with no incoming calls).
     *
     * @return array<string>
     */
    public function getEntryPoints(): array
    {
        $pdo = $this->getConnection();
        $entryPoints = [];

        $stmt = $pdo->query('SELECT fqn FROM usage_summary WHERE is_entry_point = 1');

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entryPoints[] = $row['fqn'];
        }

        return $entryPoints;
    }

    /**
     * Get references by call type.
     *
     * @return array<CallReference>
     */
    public function getByCallType(CallType $type): array
    {
        $pdo = $this->getConnection();
        $references = [];

        $stmt = $pdo->prepare('SELECT * FROM call_references WHERE call_type = :type');
        $stmt->execute(['type' => $type->value]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $references[] = $this->rowToReference($row);
        }

        return $references;
    }

    /**
     * Get references for a file.
     *
     * @return array<CallReference>
     */
    public function getByFile(string $file): array
    {
        $pdo = $this->getConnection();
        $references = [];

        $stmt = $pdo->prepare('SELECT * FROM call_references WHERE file = :file');
        $stmt->execute(['file' => $file]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $references[] = $this->rowToReference($row);
        }

        return $references;
    }

    /**
     * Get high confidence references only.
     *
     * @return array<CallReference>
     */
    public function getHighConfidenceReferences(float $minConfidence = 0.9): array
    {
        $pdo = $this->getConnection();
        $references = [];

        $stmt = $pdo->prepare('SELECT * FROM call_references WHERE confidence >= :min');
        $stmt->execute(['min' => $minConfidence]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $references[] = $this->rowToReference($row);
        }

        return $references;
    }

    /**
     * Get statistics.
     */
    public function getStats(): array
    {
        $pdo = $this->getConnection();

        $total = (int) $pdo->query('SELECT COUNT(*) FROM call_references')->fetchColumn();
        $callers = (int) $pdo->query('SELECT COUNT(DISTINCT caller_fqn) FROM call_references')->fetchColumn();
        $callees = (int) $pdo->query('SELECT COUNT(DISTINCT callee_fqn) FROM call_references')->fetchColumn();
        $entryPoints = (int) $pdo->query('SELECT COUNT(*) FROM usage_summary WHERE is_entry_point = 1')->fetchColumn();

        // Count by type
        $byType = [];
        $stmt = $pdo->query('SELECT call_type, COUNT(*) as count FROM call_references GROUP BY call_type');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $byType[$row['call_type']] = (int) $row['count'];
        }

        return [
            'total_references' => $total,
            'unique_callers' => $callers,
            'unique_callees' => $callees,
            'entry_points' => $entryPoints,
            'by_type' => $byType,
        ];
    }

    /**
     * Save analysis metadata.
     */
    public function saveMetadata(array $metadata): void
    {
        $pdo = $this->getConnection();

        $pdo->exec('DELETE FROM usage_metadata');

        $stmt = $pdo->prepare('INSERT INTO usage_metadata (key, value) VALUES (:key, :value)');

        foreach ($metadata as $key => $value) {
            $stmt->execute([
                'key' => $key,
                'value' => is_array($value) ? json_encode($value) : (string) $value,
            ]);
        }
    }

    /**
     * Load analysis metadata.
     */
    public function loadMetadata(): array
    {
        $pdo = $this->getConnection();
        $metadata = [];

        $stmt = $pdo->query('SELECT key, value FROM usage_metadata');

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['value'];
            $decoded = json_decode($value, true);
            $metadata[$row['key']] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $metadata;
    }

    /**
     * Check if storage has data.
     */
    public function hasData(): bool
    {
        if (! file_exists($this->getDatabasePath())) {
            return false;
        }

        $pdo = $this->getConnection();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM call_references')->fetchColumn();

        return $count > 0;
    }

    /**
     * Clear all data.
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
     * Save usage summaries.
     */
    private function saveSummaries(CallGraph $graph, PDO $pdo): void
    {
        $stmt = $pdo->prepare('
            INSERT INTO usage_summary (fqn, incoming_count, outgoing_count, max_depth, is_entry_point)
            VALUES (:fqn, :incoming, :outgoing, :depth, :entry)
        ');

        $allFqns = array_unique(array_merge(
            $graph->getCallers(),
            $graph->getCallees(),
        ));

        $entryPoints = $graph->getEntryPoints();

        foreach ($allFqns as $fqn) {
            $stmt->execute([
                'fqn' => $fqn,
                'incoming' => $graph->getIncomingCount($fqn),
                'outgoing' => $graph->getOutgoingCount($fqn),
                'depth' => 0, // TODO: Calculate actual depth
                'entry' => in_array($fqn, $entryPoints, true) ? 1 : 0,
            ]);
        }
    }

    /**
     * Convert database row to CallReference.
     */
    private function rowToReference(array $row): CallReference
    {
        return new CallReference(
            callerFqn: $row['caller_fqn'],
            calleeFqn: $row['callee_fqn'],
            callType: CallType::from($row['call_type']),
            file: $row['file'],
            line: (int) $row['line'],
            confidence: (float) $row['confidence'],
            context: json_decode($row['context'], true) ?? [],
        );
    }

    /**
     * Get database path.
     */
    private function getDatabasePath(): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . 'usage.db';
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
        if (! is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0o755, true);
        }
    }

    /**
     * Initialize database schema.
     */
    private function initializeSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS call_references (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                caller_fqn TEXT NOT NULL,
                callee_fqn TEXT NOT NULL,
                call_type TEXT NOT NULL,
                file TEXT NOT NULL,
                line INTEGER NOT NULL,
                confidence REAL NOT NULL,
                context TEXT
            )
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS usage_summary (
                fqn TEXT PRIMARY KEY,
                incoming_count INTEGER NOT NULL,
                outgoing_count INTEGER NOT NULL,
                max_depth INTEGER NOT NULL,
                is_entry_point INTEGER NOT NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS usage_metadata (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )
        ');

        // Create indexes for faster lookups
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_caller ON call_references(caller_fqn)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_callee ON call_references(callee_fqn)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_file ON call_references(file)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_type ON call_references(call_type)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_confidence ON call_references(confidence)');
    }
}
