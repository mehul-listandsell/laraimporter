<?php

namespace LaraImporter\Jobs;

use LaraImporter\Models\ImportJob;
use LaraImporter\Services\DatabaseConnectionService;
use LaraImporter\Services\ImportEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(protected int $importJobId) {}

    public function handle(DatabaseConnectionService $dbService, ImportEngine $importEngine): void
    {
        $importJob = ImportJob::findOrFail($this->importJobId);
        if (in_array($importJob->status, ['completed', 'failed'])) return;

        $importJob->markProcessing();

        try {
            $credentials = $importJob->connection;
            if ($credentials) {
                $result = $dbService->connect($credentials);
                if (!$result['success']) { $importJob->markFailed('DB connection failed: ' . $result['message']); return; }
            } else {
                $result = $dbService->connectFromConfig();
                if (!$result['success']) { $importJob->markFailed('DB connection failed: ' . $result['message']); return; }
            }

            $rows = [];
            if ($importJob->file_path && file_exists($importJob->file_path)) {
                $data = json_decode(file_get_contents($importJob->file_path), true);
                $rows = $data['rows'] ?? [];
            }
            if (empty($rows)) { $importJob->markFailed('No data found.'); return; }

            $results = $importEngine->executeWithProgress($rows, $importJob->config, $importJob);

            if ($importJob->file_path && file_exists($importJob->file_path)) unlink($importJob->file_path);

            $importJob->update(['inserted' => $results['inserted'], 'updated' => $results['updated'], 'skipped' => $results['skipped'], 'errors' => $results['errors']]);
            $importJob->markCompleted();
        } catch (\Throwable $e) {
            $importJob->markFailed($e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        ImportJob::find($this->importJobId)?->markFailed('Queue job failed: ' . $exception->getMessage());
    }
}
