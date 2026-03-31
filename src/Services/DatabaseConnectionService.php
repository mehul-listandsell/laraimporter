<?php

namespace LaraImporter\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use PDOException;

class DatabaseConnectionService
{
    protected string $connectionName = 'laraimporter_dynamic';

    /**
     * Connect using config setting or user-provided credentials.
     */
    public function connectFromConfig(): array
    {
        $mode = config('laraimporter.connection', 'default');

        if ($mode === 'external') {
            return ['success' => false, 'message' => 'External mode requires credentials.'];
        }

        if ($mode === 'default') {
            $this->connectionName = config('database.default');
            return ['success' => true, 'message' => 'Using default connection.'];
        }

        // Specific connection name from config/database.php
        if (Config::has("database.connections.{$mode}")) {
            $this->connectionName = $mode;
            try {
                DB::connection($this->connectionName)->getPdo();
                return ['success' => true, 'message' => "Connected via {$mode}."];
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

        return ['success' => false, 'message' => "Connection '{$mode}' not found in database config."];
    }

    /**
     * Configure and test a dynamic database connection from user credentials.
     */
    public function connect(array $credentials): array
    {
        $driver = $credentials['driver'] ?? 'mysql';

        if ($driver === 'mongodb') {
            return $this->connectMongoDB($credentials);
        }

        $config = [
            'driver' => $driver,
            'host' => $credentials['host'],
            'port' => $credentials['port'] ?? ($driver === 'pgsql' ? '5432' : '3306'),
            'database' => $credentials['database'],
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'collation' => $driver === 'pgsql' ? null : 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'options' => [
                \PDO::ATTR_TIMEOUT => 10,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ],
        ];

        Config::set("database.connections.{$this->connectionName}", $config);
        DB::purge($this->connectionName);

        try {
            DB::connection($this->connectionName)->getPdo();
            return ['success' => true, 'message' => 'Connected successfully'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function connectMongoDB(array $credentials): array
    {
        if (!extension_loaded('mongodb')) {
            return ['success' => false, 'message' => 'PHP MongoDB extension is not installed.'];
        }

        $config = [
            'driver' => 'mongodb',
            'host' => $credentials['host'] ?? '127.0.0.1',
            'port' => (int) ($credentials['port'] ?? 27017),
            'database' => $credentials['database'],
            'username' => $credentials['username'] ?: null,
            'password' => $credentials['password'] ?: null,
            'options' => ['database' => $credentials['authentication_database'] ?? 'admin'],
        ];

        Config::set("database.connections.{$this->connectionName}", $config);
        DB::purge($this->connectionName);

        try {
            DB::connection($this->connectionName)->getMongoDB()->listCollections();
            return ['success' => true, 'message' => 'Connected to MongoDB successfully'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function isExternalMode(): bool
    {
        return config('laraimporter.connection') === 'external';
    }

    public function isMongoDB(): bool
    {
        try {
            return DB::connection($this->connectionName)->getDriverName() === 'mongodb';
        } catch (\Throwable) {
            return false;
        }
    }

    public function getTables(): array
    {
        $connection = DB::connection($this->connectionName);

        if ($this->isMongoDB()) {
            return $this->getMongoCollections();
        }

        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $tables = $connection->select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name");
            return array_map(fn($t) => $t->table_name, $tables);
        }

        $tables = $connection->select('SHOW TABLES');
        $key = array_key_first((array) $tables[0]);
        return array_map(fn($t) => $t->$key, $tables);
    }

    protected function getMongoCollections(): array
    {
        $db = DB::connection($this->connectionName)->getMongoDB();
        $collections = [];
        foreach ($db->listCollections() as $collection) {
            $name = $collection->getName();
            if (!str_starts_with($name, 'system.')) {
                $collections[] = $name;
            }
        }
        sort($collections);
        return $collections;
    }

    public function getColumns(string $table): array
    {
        if ($this->isMongoDB()) {
            return $this->getMongoFields($table);
        }

        $connection = DB::connection($this->connectionName);
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $columns = $connection->select(
                "SELECT column_name, data_type, is_nullable, column_default, character_maximum_length FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? ORDER BY ordinal_position",
                [$table]
            );
            return array_map(fn($col) => [
                'name' => $col->column_name, 'type' => $col->data_type, 'nullable' => $col->is_nullable === 'YES',
                'default' => $col->column_default, 'max_length' => $col->character_maximum_length,
                'is_auto' => str_contains($col->column_default ?? '', 'nextval'),
            ], $columns);
        }

        $columns = $connection->select("SHOW FULL COLUMNS FROM `{$table}`");
        return array_map(fn($col) => [
            'name' => $col->Field, 'type' => $col->Type, 'nullable' => $col->Null === 'YES',
            'default' => $col->Default, 'max_length' => $this->extractLength($col->Type),
            'is_auto' => str_contains($col->Extra, 'auto_increment'),
        ], $columns);
    }

    protected function getMongoFields(string $collection): array
    {
        $db = DB::connection($this->connectionName)->getMongoDB();
        $coll = $db->selectCollection($collection);
        $cursor = $coll->find([], ['limit' => 100]);
        $fieldTypes = [];

        foreach ($cursor as $doc) {
            $this->discoverMongoFields((array) $doc, '', $fieldTypes);
        }

        if (!isset($fieldTypes['_id'])) $fieldTypes['_id'] = 'ObjectId';

        $columns = [];
        ksort($fieldTypes);
        foreach ($fieldTypes as $name => $type) {
            $columns[] = ['name' => $name, 'type' => $type, 'nullable' => true, 'default' => null, 'max_length' => null, 'is_auto' => $name === '_id'];
        }
        return $columns;
    }

    protected function discoverMongoFields(array $doc, string $prefix, array &$fieldTypes): void
    {
        foreach ($doc as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            $type = $this->getMongoFieldType($value);
            if (is_object($value) || (is_array($value) && !array_is_list($value))) {
                $fieldTypes[$fullKey] = 'object';
                if (is_array($value) || $value instanceof \stdClass) {
                    $this->discoverMongoFields((array) $value, $fullKey, $fieldTypes);
                }
            } else {
                if (!isset($fieldTypes[$fullKey]) || $fieldTypes[$fullKey] === 'null') {
                    $fieldTypes[$fullKey] = $type;
                }
            }
        }
    }

    protected function getMongoFieldType($value): string
    {
        if ($value === null) return 'null';
        if (is_string($value)) return 'string';
        if (is_int($value)) return 'integer';
        if (is_float($value)) return 'double';
        if (is_bool($value)) return 'boolean';
        if (is_array($value)) return array_is_list($value) ? 'array' : 'object';
        if (is_object($value)) return 'object';
        return gettype($value);
    }

    public function getForeignKeys(string $table): array
    {
        if ($this->isMongoDB()) return $this->guessMongoReferences($table);

        $connection = DB::connection($this->connectionName);
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();

        if ($driver === 'pgsql') {
            $fks = $connection->select("
                SELECT kcu.column_name, ccu.table_name AS foreign_table, ccu.column_name AS foreign_column
                FROM information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
                JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
                WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_name = ?", [$table]);
        } else {
            $fks = $connection->select("
                SELECT COLUMN_NAME as column_name, REFERENCED_TABLE_NAME as foreign_table, REFERENCED_COLUMN_NAME as foreign_column
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL", [$database, $table]);
        }

        return array_map(fn($fk) => ['column' => $fk->column_name, 'foreign_table' => $fk->foreign_table, 'foreign_column' => $fk->foreign_column], $fks);
    }

    protected function guessMongoReferences(string $collection): array
    {
        $columns = $this->getMongoFields($collection);
        $allCollections = $this->getMongoCollections();
        $refs = [];
        foreach ($columns as $col) {
            if (preg_match('/^(.+?)_?[Ii]d$/', $col['name'], $matches)) {
                $possible = $matches[1];
                $candidates = [$possible, $possible . 's', $possible . 'es', str_replace('_', '', $possible) . 's'];
                foreach ($candidates as $candidate) {
                    if (in_array($candidate, $allCollections) && $candidate !== $collection) {
                        $refs[] = ['column' => $col['name'], 'foreign_table' => $candidate, 'foreign_column' => '_id'];
                        break;
                    }
                }
            }
        }
        return $refs;
    }

    public function getPivotTables(string $table): array
    {
        if ($this->isMongoDB()) return [];

        $allTables = $this->getTables();
        $pivots = [];
        $found = [];

        // Method 1: Formal FK constraints
        foreach ($allTables as $candidateTable) {
            if ($candidateTable === $table) continue;
            $fks = $this->getForeignKeys($candidateTable);
            if (count($fks) < 2) continue;
            $linksToUs = null;
            $linksToOther = [];
            foreach ($fks as $fk) {
                if ($fk['foreign_table'] === $table) $linksToUs = $fk;
                else $linksToOther[] = $fk;
            }
            if ($linksToUs && !empty($linksToOther)) {
                $columns = $this->getColumns($candidateTable);
                $nonFkCols = array_filter($columns, function ($col) use ($fks) {
                    if ($col['is_auto']) return false;
                    foreach ($fks as $fk) { if ($col['name'] === $fk['column']) return false; }
                    return true;
                });
                foreach ($linksToOther as $otherFk) {
                    $key = $candidateTable . ':' . $otherFk['foreign_table'];
                    if (!isset($found[$key])) {
                        $pivots[] = ['pivot_table' => $candidateTable, 'primary_fk_column' => $linksToUs['column'], 'primary_reference' => $linksToUs['foreign_column'], 'related_table' => $otherFk['foreign_table'], 'related_fk_column' => $otherFk['column'], 'related_reference' => $otherFk['foreign_column'], 'extra_columns' => array_values(array_map(fn($c) => $c['name'], $nonFkCols))];
                        $found[$key] = true;
                    }
                }
            }
        }

        // Method 2: Naming convention
        $singularTable = rtrim($table, 's');
        foreach ($allTables as $candidateTable) {
            if ($candidateTable === $table) continue;
            if (!str_contains($candidateTable, $singularTable) && !str_contains($candidateTable, $table)) continue;
            try { $columns = $this->getColumns($candidateTable); } catch (\Throwable) { continue; }
            $columnNames = array_map(fn($c) => $c['name'], $columns);
            $idColumns = array_filter($columnNames, fn($c) => str_ends_with($c, '_id'));
            if (count($idColumns) < 2) continue;

            $primaryFkCol = null;
            $otherFkCols = [];
            foreach ($idColumns as $idCol) {
                $base = str_replace('_id', '', $idCol);
                $primaryCandidates = [$base, $base . 's', $base . 'es', preg_replace('/y$/', 'ies', $base)];
                if (in_array($table, $primaryCandidates) || $base === $singularTable) $primaryFkCol = $idCol;
                else $otherFkCols[] = $idCol;
            }
            if (!$primaryFkCol || empty($otherFkCols)) continue;

            foreach ($otherFkCols as $otherCol) {
                $otherBase = str_replace('_id', '', $otherCol);
                $candidates = [$otherBase, $otherBase . 's', $otherBase . 'es', preg_replace('/y$/', 'ies', $otherBase), preg_replace('/s$/', 'ses', $otherBase)];
                $relatedTable = null;
                foreach ($allTables as $t) { if (in_array($t, $candidates)) { $relatedTable = $t; break; } }
                if (!$relatedTable) continue;
                $key = $candidateTable . ':' . $relatedTable;
                if (isset($found[$key])) continue;
                $nonFkCols = array_filter($columns, fn($col) => !$col['is_auto'] && $col['name'] !== $primaryFkCol && $col['name'] !== $otherCol);
                $pivots[] = ['pivot_table' => $candidateTable, 'primary_fk_column' => $primaryFkCol, 'primary_reference' => 'id', 'related_table' => $relatedTable, 'related_fk_column' => $otherCol, 'related_reference' => 'id', 'extra_columns' => array_values(array_map(fn($c) => $c['name'], $nonFkCols))];
                $found[$key] = true;
            }
        }

        return $pivots;
    }

    public function getConnection() { return DB::connection($this->connectionName); }
    public function getConnectionName(): string { return $this->connectionName; }

    protected function extractLength(string $type): ?int
    {
        return preg_match('/\((\d+)\)/', $type, $m) ? (int) $m[1] : null;
    }
}
