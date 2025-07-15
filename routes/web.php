<?php

use App\Http\Controllers\CreateFromWebhookSyncFlowController;
use App\Http\Controllers\WebhookZohoController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\CadenciaIndex;
use App\Livewire\EtapasIndex;
use App\Livewire\FaqIndex;
use App\Livewire\GroupSendIndex;
use App\Livewire\LeadConversationHistory;
use App\Livewire\LeadDetails;
use App\Livewire\ListContatosIndex;
use App\Livewire\NotificationsIndex;
use App\Livewire\PerfilIndex;
use App\Livewire\ScriptsIndex;
use App\Livewire\SendIndex;
use App\Livewire\StatisticIndex;
use App\Livewire\SyncFlowLeads;
use App\Livewire\UserConfigIndex;
use App\Livewire\UsersIndex;
use App\Livewire\WebhookLogsIndex;
use App\Livewire\WebhookTypeIndex;
use Illuminate\Support\Facades\Route;
use App\Livewire\ProcessLogsIndex;

// Webhook Padrão Bulkship SyncFlow
Route::post('/webhook-bulkship-syncflow', [CreateFromWebhookSyncFlowController::class, 'createFromWebhookSyncFlow']);

Route::redirect('/', '/login');

// Statistic
Route::get('/statistic', StatisticIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('statistic.index');

// Dashboard
Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Users
Route::get('/users', UsersIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('users.index');

// User config
Route::get('/users-config/{userId?}', UserConfigIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('users.config');

Route::get('/login', Login::class)
    ->middleware('guest')
    ->name('login');

Route::get('register', Register::class)
    ->middleware('guest')
    ->name('register');

Route::get('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/login');
})->name('logout');

// Send
Route::get('/send/create/group-{groupId}', SendIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('send.index');

// Group
Route::get('/group-send', GroupSendIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('group-send.index');

// User perfil
Route::get('/perfil/{id?}', PerfilIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('perfil.index');

// User perfil
Route::get('/contatos', ListContatosIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('contatos.index');

// Gerenciar cadencia
Route::get('/cadencias', CadenciaIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('cadencias.index');

Route::get('/cadencias/{cadenciaId}/etapas', EtapasIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('etapas.index');

Route::get('/sync-flow', SyncFlowLeads::class)
    ->middleware(['auth', 'verified'])
    ->name('sync-flow.index');

Route::get('/faq-info', FaqIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('faq-info.index');

Route::get('/leads/history/{leadId}', LeadConversationHistory::class)
    ->name('lead.conversation.history');

// Notifications
Route::get('/notifications-index', NotificationsIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('notifications.index');

// Scripts
Route::get('/scrpits-index', ScriptsIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('scripts.index');

// Webhook Types Selection
Route::get('/webhook-types', WebhookTypeIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('webhook-types.index');

// Webhook Logs
Route::get('/webhook-logs/{userId?}', WebhookLogsIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('webhook-logs.index');

// System Logs
Route::get('/processos-logs', ProcessLogsIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('processos-logs.index');

// Details of SyncFlow Leads
Route::get('/lead/{leadId}/details', LeadDetails::class)
    ->middleware(['auth', 'verified'])
    ->name('lead.details');

require __DIR__.'/auth.php';
