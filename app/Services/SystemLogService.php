<?php

namespace App\Services;

use App\Models\SystemLog;
use Illuminate\Support\Facades\Log;

class SystemLogService
{
    protected $maxActiveLogs = 1000;

    public function info(string $message, array $context = [])
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = [])
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = [])
    {
        $this->log('error', $message, $context);
    }

    protected function log(string $type, string $message, array $context)
    {
        try {
            // Check active log count and archive oldest if needed
            $activeLogCount = SystemLog::where('archived', false)->count();
            if ($activeLogCount >= $this->maxActiveLogs) {
                $logsToArchive = SystemLog::where('archived', false)
                    ->orderBy('created_at', 'asc')
                    ->take($activeLogCount - $this->maxActiveLogs + 1)
                    ->get();

                foreach ($logsToArchive as $log) {
                    $log->update(['archived' => true]);
                }

                Log::info('Arquivados logs antigos automaticamente', [
                    'count' => $logsToArchive->count(),
                    'log_ids' => $logsToArchive->pluck('id')->toArray(),
                ]);
            }

            SystemLog::create([
                'type' => $type,
                'message' => $message,
                'context' => !empty($context) ? $context : null,
                'created_at' => now(),
                'archived' => false,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to save system log: {$e->getMessage()}", [
                'type' => $type,
                'message' => $message,
                'context' => $context,
                'exception' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'trace' => array_slice($e->getTrace(), 0, 5),
                ],
            ]);
        }
    }
}