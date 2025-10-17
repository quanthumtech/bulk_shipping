<?php

use App\Http\Controllers\Clients\BiUP\WebhookZohoController;
use App\Http\Controllers\CreateFromWebhookSyncFlowController;
use App\Http\Controllers\WebhookChatWootController;
use Illuminate\Support\Facades\Route;

// Webhook Zoho CRM - Bulkship BiUP
Route::post('/webhook-bulkship-biup', [WebhookZohoController::class, 'createFromWebhook']);

// Webhook Chatwoot
Route::post('/chatwoot_webhook', [WebhookChatWootController::class, 'handleWebhook']);

// Webhook Padrão Bulkship SyncFlow
Route::post('/webhook-bulkship-syncflow', [CreateFromWebhookSyncFlowController::class, 'createFromWebhookSyncFlow']);
