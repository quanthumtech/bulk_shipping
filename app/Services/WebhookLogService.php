<?php

namespace App\Services;

use App\Models\WebhookLog;
use Illuminate\Support\Facades\Log;

class WebhookLogService
{
    protected $maxActiveLogs = 1000;

    public function log(string $type, string $message, array $context = [], ?string $chatwootAccountId = null, ?int $userId = null, ?string $webhook_type = null): void
    {
        try {
            // Check active log count and archive oldest if needed
            $activeLogCount = WebhookLog::where('archived', false)->count();
            if ($activeLogCount >= $this->maxActiveLogs) {
                $logsToArchive = WebhookLog::where('archived', false)
                    ->orderBy('created_at', 'asc')
                    ->take($activeLogCount - $this->maxActiveLogs + 1)
                    ->get();

                foreach ($logsToArchive as $log) {
                    $log->update(['archived' => true]);
                }

            }

            WebhookLog::create([
                'user_id' => $userId,
                'type' => $type,
                'webhook_type' => $webhook_type,
                'message' => $message,
                'context' => $context,
                'chatwoot_account_id' => $chatwootAccountId,
                'archived' => false,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to save webhook log: {$e->getMessage()}", [
                'type' => $type,
                'message' => $message,
                'context' => $context,
                'chatwoot_account_id' => $chatwootAccountId,
                'user_id' => $userId,
                'webhook_type' => $webhook_type,
                'exception' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'trace' => array_slice($e->getTrace(), 0, 5),
                ],
            ]);
        }
    }

    public function info(string $message, array $context = [], ?string $chatwootAccountId = null, ?int $userId = null, ?string $webhook_type): void
    {
        $this->log('info', $message, $context, $chatwootAccountId, $userId, $webhook_type);
    }

    public function warning(string $message, array $context = [], ?string $chatwootAccountId = null, ?int $userId = null, ?string $webhook_type): void
    {
        $this->log('warning', $message, $context, $chatwootAccountId, $userId, $webhook_type);
    }

    public function error(string $message, array $context = [], ?string $chatwootAccountId = null, ?int $userId = null, ?string $webhook_type): void
    {
        $this->log('error', $message, $context, $chatwootAccountId, $userId, $webhook_type);
    }
}