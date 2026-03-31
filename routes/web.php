<?php

use Illuminate\Support\Facades\Route;
use LaraImporter\Http\Controllers\ImportController;

$prefix = config('laraimporter.route_prefix', 'admin/import');
$middleware = config('laraimporter.middleware', ['web', 'auth']);

Route::middleware($middleware)->prefix($prefix)->group(function () {
    Route::get('/', [ImportController::class, 'index'])->name('laraimporter.index');
    Route::get('/history', [ImportController::class, 'history'])->name('laraimporter.history');
    Route::post('/test-connection', [ImportController::class, 'testConnection'])->name('laraimporter.test-connection');
    Route::post('/upload-file', [ImportController::class, 'uploadFile'])->name('laraimporter.upload-file');
    Route::get('/get-tables', [ImportController::class, 'getTables'])->name('laraimporter.get-tables');
    Route::post('/get-columns', [ImportController::class, 'getColumns'])->name('laraimporter.get-columns');
    Route::post('/select-table', [ImportController::class, 'selectTable'])->name('laraimporter.select-table');
    Route::post('/preview', [ImportController::class, 'preview'])->name('laraimporter.preview');
    Route::post('/execute', [ImportController::class, 'execute'])->name('laraimporter.execute');
    Route::post('/job-status', [ImportController::class, 'jobStatus'])->name('laraimporter.job-status');
});
