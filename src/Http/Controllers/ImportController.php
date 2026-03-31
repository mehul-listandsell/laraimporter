<?php

namespace LaraImporter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use LaraImporter\Jobs\ProcessImportJob;
use LaraImporter\Models\ImportJob;
use LaraImporter\Services\DatabaseConnectionService;
use LaraImporter\Services\FileParserService;
use LaraImporter\Services\ImportEngine;

class ImportController extends Controller
{
    public function __construct(
        protected DatabaseConnectionService $dbService,
        protected FileParserService $fileParser,
        protected ImportEngine $importEngine,
    ) {}

    public function index()
    {
        Session::forget('laraimporter');

        $connectionMode = config('laraimporter.connection', 'default');
        $isExternal = $connectionMode === 'external';

        // For non-external mode, auto-connect now
        if (!$isExternal) {
            $result = $this->dbService->connectFromConfig();
            if ($result['success']) {
                Session::put('laraimporter.connected', true);
            }
        }

        return view('laraimporter::wizard', [
            'isExternal' => $isExternal,
            'mongoAvailable' => extension_loaded('mongodb'),
            'queueThreshold' => config('laraimporter.queue_threshold', 1000),
        ]);
    }

    public function history()
    {
        $jobs = ImportJob::where('user_id', Auth::id())->orderByDesc('created_at')->paginate(20);
        return view('laraimporter::history', compact('jobs'));
    }

    public function testConnection(Request $request)
    {
        $request->validate([
            'driver' => 'required|in:' . implode(',', config('laraimporter.drivers', ['mysql', 'pgsql', 'mongodb'])),
            'host' => 'required|string', 'port' => 'required|numeric', 'database' => 'required|string',
            'username' => 'nullable|string', 'password' => 'nullable|string', 'authentication_database' => 'nullable|string',
        ]);

        $credentials = $request->only(['driver', 'host', 'port', 'database', 'username', 'password', 'authentication_database']);
        $result = $this->dbService->connect($credentials);

        if ($result['success']) {
            Session::put('laraimporter.connection', $credentials);
            Session::put('laraimporter.connected', true);
        }

        return response()->json($result);
    }

    public function uploadFile(Request $request)
    {
        $maxSize = config('laraimporter.max_upload_size', 51200);
        $request->validate(['file' => "required|file|mimes:csv,txt,json,xls,xlsx,ods|max:{$maxSize}"]);

        if (!Session::get('laraimporter.connected')) {
            return response()->json(['success' => false, 'message' => 'No database connection.'], 422);
        }

        try {
            $parsed = $this->fileParser->parse($request->file('file'));
            $preview = $this->fileParser->getPreview($parsed, 10);

            $tempPath = storage_path('app/laraimporter_temp_' . uniqid() . '.json');
            file_put_contents($tempPath, json_encode($parsed));
            Session::put('laraimporter.file_temp_path', $tempPath);

            if ($parsed['total_rows'] <= 5000) Session::put('laraimporter.file_data', $parsed);

            Session::put('laraimporter.file_headers', $parsed['headers']);
            Session::put('laraimporter.file_total_rows', $parsed['total_rows']);

            $threshold = config('laraimporter.queue_threshold', 1000);

            return response()->json([
                'success' => true, 'preview' => $preview,
                'total_rows' => $parsed['total_rows'],
                'suggest_queue' => $parsed['total_rows'] > $threshold,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function getTables()
    {
        $this->ensureConnected();

        try {
            return response()->json([
                'success' => true,
                'tables' => $this->dbService->getTables(),
                'is_mongodb' => $this->dbService->isMongoDB(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getColumns(Request $request)
    {
        $request->validate(['table' => 'required|string']);
        $this->ensureConnected();

        try {
            $pivotTables = [];
            try { $pivotTables = $this->dbService->getPivotTables($request->table); } catch (\Throwable) {}

            return response()->json([
                'success' => true,
                'columns' => $this->dbService->getColumns($request->table),
                'foreign_keys' => $this->dbService->getForeignKeys($request->table),
                'pivot_tables' => $pivotTables,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function selectTable(Request $request)
    {
        $request->validate(['primary_table' => 'required|string', 'related_tables' => 'nullable|array']);
        $this->ensureConnected();

        Session::put('laraimporter.primary_table', $request->primary_table);

        $relatedColumnsData = [];
        foreach ($request->related_tables ?? [] as $relatedTable) {
            $relatedColumnsData[$relatedTable] = [
                'columns' => $this->dbService->getColumns($relatedTable),
                'foreign_keys' => $this->dbService->getForeignKeys($relatedTable),
            ];
        }

        $fileData = Session::get('laraimporter.file_data');
        $headers = $fileData ? $fileData['headers'] : Session::get('laraimporter.file_headers', []);

        return response()->json([
            'success' => true, 'file_headers' => $headers,
            'primary_table' => [
                'name' => $request->primary_table,
                'columns' => $this->dbService->getColumns($request->primary_table),
                'foreign_keys' => $this->dbService->getForeignKeys($request->primary_table),
            ],
            'related_tables' => $relatedColumnsData,
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'mapping' => 'required|array', 'related_config' => 'nullable|array',
            'duplicate_handling' => 'nullable|in:skip,update,create', 'duplicate_check_column' => 'nullable|string',
            'default_values' => 'nullable|array', 'pivot_config' => 'nullable|array',
        ]);

        $this->ensureConnected();

        $importConfig = [
            'primary_table' => Session::get('laraimporter.primary_table'),
            'mapping' => $request->mapping,
            'related_tables' => $request->related_config ?? [],
            'duplicate_handling' => $request->duplicate_handling ?? 'create',
            'duplicate_check_column' => $request->duplicate_check_column,
            'default_values' => $request->default_values ?? [],
            'pivot_tables' => $request->pivot_config ?? [],
        ];

        Session::put('laraimporter.config', $importConfig);
        $rows = $this->getImportRows();
        $totalRows = Session::get('laraimporter.file_total_rows', count($rows));

        try {
            $preview = $this->importEngine->dryRun($rows, $importConfig);
            $preview['total_rows'] = $totalRows;
            $preview['suggest_queue'] = $totalRows > config('laraimporter.queue_threshold', 1000);
            return response()->json(['success' => true, 'preview' => $preview]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function execute(Request $request)
    {
        $request->validate(['use_queue' => 'nullable|boolean']);

        $importConfig = Session::get('laraimporter.config');
        if (!$importConfig) return response()->json(['success' => false, 'message' => 'Import session expired.'], 422);

        $useQueue = $request->boolean('use_queue', false);
        $tempPath = Session::get('laraimporter.file_temp_path');
        $totalRows = Session::get('laraimporter.file_total_rows', 0);
        $credentials = Session::get('laraimporter.connection'); // null for default connection

        if ($useQueue) {
            $importJob = ImportJob::create([
                'user_id' => Auth::id(), 'status' => 'pending', 'total_rows' => $totalRows,
                'config' => $importConfig, 'connection' => $credentials, 'file_path' => $tempPath,
            ]);
            ProcessImportJob::dispatch($importJob->id);
            Session::forget('laraimporter');
            return response()->json(['success' => true, 'queued' => true, 'job_id' => $importJob->id]);
        }

        $this->ensureConnected();
        $rows = $this->getImportRows();

        try {
            $results = $this->importEngine->execute($rows, $importConfig);
            if ($tempPath && file_exists($tempPath)) unlink($tempPath);
            Session::forget('laraimporter');
            return response()->json(['success' => true, 'results' => $results, 'queued' => false]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function jobStatus(Request $request)
    {
        $request->validate(['job_id' => 'required|integer']);
        $job = ImportJob::where('id', $request->job_id)->where('user_id', Auth::id())->first();
        if (!$job) return response()->json(['success' => false, 'message' => 'Not found.'], 404);

        return response()->json(['success' => true, 'job' => [
            'id' => $job->id, 'status' => $job->status, 'total_rows' => $job->total_rows,
            'processed_rows' => $job->processed_rows, 'inserted' => $job->inserted, 'updated' => $job->updated,
            'skipped' => $job->skipped, 'errors' => $job->errors ?? [], 'error_message' => $job->error_message,
            'progress' => $job->getProgressPercentage(),
        ]]);
    }

    protected function ensureConnected(): void
    {
        if (Session::get('laraimporter.connected')) {
            $credentials = Session::get('laraimporter.connection');
            if ($credentials) {
                $this->dbService->connect($credentials);
            } else {
                $this->dbService->connectFromConfig();
            }
            return;
        }
        abort(422, 'No database connection.');
    }

    protected function getImportRows(): array
    {
        $fileData = Session::get('laraimporter.file_data');
        if ($fileData) return $fileData['rows'];

        $tempPath = Session::get('laraimporter.file_temp_path');
        if ($tempPath && file_exists($tempPath)) {
            return json_decode(file_get_contents($tempPath), true)['rows'] ?? [];
        }
        return [];
    }
}
