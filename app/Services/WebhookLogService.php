<?php

namespace App\Services;

use App\Models\WebhookLog;

class WebhookLogService
{
    public function log(string $type, string $message, array $context = [], ?string $chatwootAccountId = null, ?int $userId = null, ?string $webhook_type = null): void
    {
        WebhookLog::create([
            'user_id' => $userId,
            'type' => $type,
            'webhook_type' => $webhook_type,
            'message' => $message,
            'context' => $context,
            'chatwoot_account_id' => $chatwootAccountId,
        ]);
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
