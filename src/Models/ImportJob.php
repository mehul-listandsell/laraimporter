<?php

namespace LaraImporter\Models;

use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    protected $table = 'laraimporter_jobs';

    protected $fillable = [
        'user_id', 'status', 'total_rows', 'processed_rows', 'inserted', 'updated', 'skipped',
        'errors', 'config', 'connection', 'file_path', 'error_message', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'config' => 'array',
        'connection' => 'encrypted:array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User')); }

    public function markProcessing(): void { $this->update(['status' => 'processing', 'started_at' => now()]); }
    public function markCompleted(): void { $this->update(['status' => 'completed', 'completed_at' => now()]); }
    public function markFailed(string $message): void { $this->update(['status' => 'failed', 'error_message' => $message, 'completed_at' => now()]); }

    public function addError(array $error): void
    {
        $errors = $this->errors ?? [];
        $errors[] = $error;
        if (count($errors) > 500) $errors = array_slice($errors, -500);
        $this->update(['errors' => $errors]);
    }

    public function getProgressPercentage(): int
    {
        if ($this->total_rows === 0) return 0;
        return min(100, (int) round(($this->processed_rows / $this->total_rows) * 100));
    }

    public function isRunning(): bool { return in_array($this->status, ['pending', 'processing']); }
}
