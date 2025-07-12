<?php

namespace App\Livewire;

use App\Models\WebhookLog;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WebhookLogsIndex extends Component
{
    use WithPagination, Toast;

    public ?int $userId = null;
    public string $search = '';
    public string $typeFilter = '';
    public string $webhookTypeFilter = '';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $dataNow = null;
    public int $perPage = 10;
    public ?int $selectedLogId = null;
    public bool $showDrawer = false;
    public array $selected = [];
    public bool $showArchived = false;
    public bool $showFilterDrawer = false;

    public function mount($userId = null, $webhookType = null)
    {
        $this->userId = $userId;
        $this->webhookTypeFilter = $webhookType ?? '';
        $this->selected = [];
        if ($this->userId && !User::find($this->userId)) {
            $this->error('Usuário não encontrado.', position: 'toast-top');
            return redirect()->route('users.index');
        }
    }

    public function openModal(int $id)
    {
        $this->selectedLogId = $id;
        $this->showDrawer = true;
    }

    public function closeModal()
    {
        $this->selectedLogId = null;
        $this->showDrawer = false;
    }

    public function openFilterDrawer()
    {
        $this->showFilterDrawer = true;
    }

    public function closeFilterDrawer()
    {
        $this->showFilterDrawer = false;
    }

    public function resetFilters()
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->webhookTypeFilter = '';
        $this->startDate = null;
        $this->endDate = null;
        $this->dataNow = null;
        $this->showArchived = false;
        $this->resetPage();
        $this->success('Filtros resetados!', position: 'toast-top');
    }

    public function applyFilters()
    {
        $this->validate([
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'dataNow' => 'nullable|date',
        ]);

        if ($this->dataNow && ($this->startDate || $this->endDate)) {
            $this->error('Por favor, use apenas "Data do Log" ou "Data Inicial/Final", não ambos.', position: 'toast-top');
            return;
        }

        $this->resetPage();
        $this->showFilterDrawer = false;
        $this->success('Filtros aplicados!', position: 'toast-top');
    }

    public function deleteLog(int $id)
    {
        $log = WebhookLog::find($id);
        if ($log) {
            $log->delete();
            $this->success('Log excluído com sucesso!', position: 'toast-top');
        } else {
            $this->error('Log não encontrado.', position: 'toast-top');
        }
        $this->resetPage();
    }

    public function deleteSelected()
    {
        Log::info('deleteSelected called', ['selected' => $this->selected]);
        if (empty($this->selected)) {
            $this->error('Nenhum log selecionado.', position: 'toast-top');
            return;
        }

        WebhookLog::whereIn('id', $this->selected)->delete();
        $this->selected = [];
        $this->success('Logs selecionados excluídos com sucesso!', position: 'toast-top');
        $this->resetPage();
    }

    public function archiveSelected()
    {
        Log::info('archiveSelected called', ['selected' => $this->selected]);
        if (empty($this->selected)) {
            $this->error('Nenhum log selecionado.', position: 'toast-top');
            return;
        }

        WebhookLog::whereIn('id', $this->selected)->update(['archived' => true]);
        $this->selected = [];
        $this->success('Logs selecionados arquivados com sucesso!', position: 'toast-top');
        $this->resetPage();
    }

    public function exportSelected()
    {
        Log::info('exportSelected called', ['selected' => $this->selected]);
        if (empty($this->selected)) {
            $this->error('Nenhum log selecionado.', position: 'toast-top');
            return;
        }

        $logs = WebhookLog::whereIn('id', $this->selected)->get();
        $csvData = "ID,Tipo,Webhook Type,Mensagem,Conta Chatwoot,Data,Contexto\n";

        foreach ($logs as $log) {
            $csvData .= sprintf(
                "%s,%s,%s,\"%s\",%s,%s,\"%s\"\n",
                $log->id,
                $log->type,
                $log->webhook_type ?? 'N/A',
                str_replace('"', '""', $log->message),
                $log->chatwoot_account_id ?? 'N/A',
                $log->created_at->format('d/m/Y H:i:s'),
                str_replace('"', '""', json_encode($log->context))
            );
        }

        $filename = 'logs_' . now()->format('Ymd_His') . '.csv';
        Storage::put($filename, $csvData);

        $this->selected = [];
        return Response::download(storage_path("app/{$filename}"))->deleteFileAfterSend(true);
    }

    public function updatedSelected($value)
    {
        Log::info('Selected rows updated', ['selected' => $this->selected, 'value' => $value]);
        $this->dispatch('refresh-component');
    }

    public function updatedShowArchived()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedTypeFilter()
    {
        $this->resetPage();
    }

    public function updatedWebhookTypeFilter()
    {
        $this->resetPage();
    }

    public function updatedStartDate()
    {
        $this->resetPage();
    }

    public function updatedEndDate()
    {
        $this->resetPage();
    }

    public function updatedDataNow()
    {
        $this->resetPage();
    }

    public function getWebhookTypeOptionsProperty()
    {
        $webhookTypes = WebhookLog::select('webhook_type')
            ->distinct()
            ->whereNotNull('webhook_type')
            ->pluck('webhook_type')
            ->map(function ($type) {
                return [
                    'id' => $type,
                    'name' => ucfirst($type),
                ];
            })
            ->toArray();

        return array_merge(
            [['id' => '', 'name' => 'Todos']],
            $webhookTypes
        );
    }

    public function render()
    {
        $query = WebhookLog::query()
            ->when($this->userId, fn($q) => $q->where('user_id', $this->userId))
            ->when($this->search, fn($q) => $q->where('message', 'like', '%' . $this->search . '%'))
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->webhookTypeFilter, fn($q) => $q->where('webhook_type', $this->webhookTypeFilter))
            ->when($this->dataNow, function ($q) {
                $dateTime = Carbon::parse($this->dataNow, 'America/Sao_Paulo')->setTimezone('UTC');
                return $q->whereBetween('created_at', [
                    $dateTime->copy()->subMinute(),
                    $dateTime->copy()->addMinute(),
                ]);
            })
            ->when(!$this->dataNow && $this->startDate, fn($q) => $q->whereDate('created_at', '>=', Carbon::parse($this->startDate)->startOfDay()->setTimezone('UTC')))
            ->when(!$this->dataNow && $this->endDate, fn($q) => $q->whereDate('created_at', '<=', Carbon::parse($this->endDate)->endOfDay()->setTimezone('UTC')))
            ->when(!$this->showArchived, fn($q) => $q->where('archived', false))
            ->orderBy('created_at', 'desc');

        $logs = $query->paginate($this->perPage);

        $selectedLog = $this->selectedLogId ? WebhookLog::find($this->selectedLogId) : null;

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'type', 'label' => 'Tipo'],
            ['key' => 'webhook_type', 'label' => 'Webhook'],
            ['key' => 'message', 'label' => 'Mensagem'],
            ['key' => 'chatwoot_account_id', 'label' => 'Conta Chatwoot'],
            ['key' => 'created_at', 'label' => 'Data'],
        ];

        return view('livewire.webhook-logs-index', [
            'logs' => $logs,
            'headers' => $headers,
            'selectedLog' => $selectedLog,
            'typeOptions' => [
                ['id' => '', 'name' => 'Todos'],
                ['id' => 'info', 'name' => 'Info'],
                ['id' => 'warning', 'name' => 'Aviso'],
                ['id' => 'error', 'name' => 'Erro'],
            ],
            'webhookTypeOptions' => $this->webhookTypeOptions,
        ]);
    }
}