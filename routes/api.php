<?php

use App\Http\Controllers\WebhookZohoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhook-bulkship', [WebhookZohoController::class, 'createFromWebhook']);
