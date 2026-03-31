<?php

namespace LaraImporter\Services;

use LaraImporter\Models\ImportJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportEngine
{
    protected string $connectionName;
    protected array $results = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

    public function __construct(protected DatabaseConnectionService $dbService)
    {
        $this->connectionName = $dbService->getConnectionName();
    }

    public function execute(array $rows, array $config): array
    {
        return $this->doExecute($rows, $config, null);
    }

    public function executeWithProgress(array $rows, array $config, ImportJob $importJob): array
    {
        return $this->doExecute($rows, $config, $importJob);
    }

    protected function doExecute(array $rows, array $config, ?ImportJob $importJob): array
    {
        $this->results = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $connection = DB::connection($this->connectionName);
        $primaryTable = $config['primary_table'];
        $mapping = $config['mapping'];
        $relatedTables = $config['related_tables'] ?? [];
        $duplicateHandling = $config['duplicate_handling'] ?? 'create';
        $duplicateCheckColumn = $config['duplicate_check_column'] ?? null;
        $defaultValues = $config['default_values'] ?? [];
        $pivotTables = $config['pivot_tables'] ?? [];

        $processedCount = 0;
        $chunks = array_chunk($rows, 100, true);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $rowIndex => $row) {
                try {
                    $this->processRow($connection, $row, $primaryTable, $mapping, $relatedTables, $duplicateHandling, $duplicateCheckColumn, $defaultValues, $pivotTables);
                } catch (\Throwable $e) {
                    $this->results['errors'][] = ['row' => $rowIndex + 1, 'message' => $e->getMessage(), 'data' => array_slice($row, 0, 3)];
                    if ($importJob) $importJob->addError(['row' => $rowIndex + 1, 'message' => $e->getMessage()]);
                }
                $processedCount++;
                if ($importJob && $processedCount % 50 === 0) {
                    $importJob->update(['processed_rows' => $processedCount, 'inserted' => $this->results['inserted'], 'updated' => $this->results['updated'], 'skipped' => $this->results['skipped']]);
                }
            }
        }

        if ($importJob) {
            $importJob->update(['processed_rows' => $processedCount, 'inserted' => $this->results['inserted'], 'updated' => $this->results['updated'], 'skipped' => $this->results['skipped']]);
        }

        return $this->results;
    }

    protected function processRow($connection, array $row, string $primaryTable, array $mapping, array $relatedTables, string $duplicateHandling, ?string $duplicateCheckColumn, array $defaultValues = [], array $pivotTables = []): void
    {
        $resolvedFks = $this->resolveRelatedTables($connection, $row, $mapping, $relatedTables);

        // Build primary data: defaults → mapped values → FK IDs
        $primaryData = [];
        foreach ($defaultValues as $column => $value) {
            if ($value !== null && $value !== '') $primaryData[$column] = $value;
        }
        foreach ($mapping as $fileColumn => $target) {
            if ($target['table'] === $primaryTable) $primaryData[$target['column']] = $row[$fileColumn] ?? null;
        }
        foreach ($resolvedFks as $fkColumn => $fkValue) {
            $primaryData[$fkColumn] = $fkValue;
        }

        $primaryData = array_filter($primaryData, fn($v) => $v !== null && $v !== '');

        // Handle duplicates
        $primaryId = null;
        if ($duplicateCheckColumn && isset($primaryData[$duplicateCheckColumn])) {
            $existing = $connection->table($primaryTable)->where($duplicateCheckColumn, $primaryData[$duplicateCheckColumn])->first();
            if ($existing) {
                if ($duplicateHandling === 'skip') { $this->results['skipped']++; return; }
                if ($duplicateHandling === 'update') {
                    $connection->table($primaryTable)->where($duplicateCheckColumn, $primaryData[$duplicateCheckColumn])->update($primaryData);
                    $this->results['updated']++;
                    $primaryId = $existing->id ?? null;
                }
            }
        }

        if ($primaryId === null) {
            $primaryId = $connection->table($primaryTable)->insertGetId($primaryData);
            $this->results['inserted']++;
        }

        // Many-to-many pivots
        if ($primaryId && !empty($pivotTables)) {
            $this->processPivotTables($connection, $row, $primaryId, $pivotTables);
        }
    }

    protected function resolveRelatedTables($connection, array $row, array $mapping, array $relatedTables): array
    {
        $resolvedFks = [];
        foreach ($relatedTables as $relatedTable => $config) {
            $matchValue = null;
            foreach ($mapping as $fileColumn => $target) {
                if ($target['table'] === $relatedTable && $target['column'] === $config['match_column']) {
                    $matchValue = $row[$fileColumn] ?? null;
                    break;
                }
            }
            if (empty($matchValue)) continue;

            $existing = $connection->table($relatedTable)->where($config['match_column'], $matchValue)->first();
            if ($existing) {
                $refCol = $config['reference_column'] ?? 'id';
                $resolvedFks[$config['fk_column']] = $existing->{$refCol} ?? null;
            } elseif ($config['create_if_missing'] ?? false) {
                $relatedData = [];
                foreach ($mapping as $fileCol => $target) {
                    if ($target['table'] === $relatedTable) $relatedData[$target['column']] = $row[$fileCol] ?? null;
                }
                if (empty($relatedData[$config['match_column']])) $relatedData[$config['match_column']] = $matchValue;
                $relatedData = array_filter($relatedData, fn($v) => $v !== null && $v !== '');
                $id = $connection->table($relatedTable)->insertGetId($relatedData);
                $resolvedFks[$config['fk_column']] = $id;
            }
        }
        return $resolvedFks;
    }

    protected function processPivotTables($connection, array $row, $primaryId, array $pivotTables): void
    {
        foreach ($pivotTables as $pivotConfig) {
            $rawValue = $row[$pivotConfig['file_column'] ?? ''] ?? null;
            if (empty($rawValue)) continue;

            $separator = $pivotConfig['separator'] ?? ',';
            $values = array_filter(array_map('trim', explode($separator, $rawValue)), fn($v) => $v !== '');

            foreach ($values as $value) {
                try {
                    $related = $connection->table($pivotConfig['related_table'])->where($pivotConfig['related_match_column'], $value)->first();
                    $relatedId = $related ? ($related->{$pivotConfig['related_reference'] ?? 'id'} ?? null) : $connection->table($pivotConfig['related_table'])->insertGetId([$pivotConfig['related_match_column'] => $value]);

                    $pivotExists = $connection->table($pivotConfig['pivot_table'])->where($pivotConfig['primary_fk_column'], $primaryId)->where($pivotConfig['related_fk_column'], $relatedId)->exists();
                    if (!$pivotExists) {
                        $connection->table($pivotConfig['pivot_table'])->insert([$pivotConfig['primary_fk_column'] => $primaryId, $pivotConfig['related_fk_column'] => $relatedId]);
                    }
                } catch (\Throwable $e) {
                    $this->results['errors'][] = ['row' => 0, 'message' => "Pivot {$pivotConfig['pivot_table']}: {$value} — {$e->getMessage()}"];
                }
            }
        }
    }

    public function dryRun(array $rows, array $config): array
    {
        $preview = [];
        $connection = DB::connection($this->connectionName);
        $primaryTable = $config['primary_table'];
        $mapping = $config['mapping'];
        $defaultValues = $config['default_values'] ?? [];

        foreach (array_slice($rows, 0, 10) as $row) {
            $rowData = [];
            foreach ($defaultValues as $column => $value) {
                if ($value !== null && $value !== '') $rowData[$column] = "[default: {$value}]";
            }
            foreach ($mapping as $fileColumn => $target) {
                if ($target['table'] === $primaryTable) $rowData[$target['column']] = $row[$fileColumn] ?? null;
            }
            foreach ($config['related_tables'] ?? [] as $relatedTable => $relConfig) {
                foreach ($mapping as $fileCol => $target) {
                    if ($target['table'] === $relatedTable && $target['column'] === $relConfig['match_column']) {
                        $value = $row[$fileCol] ?? '';
                        $exists = $connection->table($relatedTable)->where($relConfig['match_column'], $value)->exists();
                        $rowData[$relConfig['fk_column']] = $exists ? "[exists: {$value}]" : ($relConfig['create_if_missing'] ? "[will create: {$value}]" : "[MISSING: {$value}]");
                        break;
                    }
                }
            }
            $preview[] = $rowData;
        }

        return ['preview' => $preview, 'total_rows' => count($rows), 'errors' => []];
    }
}
