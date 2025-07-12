<?php

namespace App\Livewire;

use App\Models\SystemLog;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Carbon\Carbon;

class ProcessLogsIndex extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public string $typeFilter = '';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $dataNow = null;
    public string $archivedFilter = 'active'; // 'active', 'archived', 'all'
    public int $perPage = 10;
    public ?int $selectedLogId = null;
    public bool $showDrawer = false;
    public bool $showFilterDrawer = false;
    public array $selectedLogs = [];

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
        $this->startDate = null;
        $this->endDate = null;
        $this->dataNow = null;
        $this->archivedFilter = 'active';
        $this->selectedLogs = [];
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

    public function archiveLog(int $id)
    {
        try {
            SystemLog::where('id', $id)->update(['archived' => true]);
            $this->success('Log arquivado com sucesso!', position: 'toast-top');
            if ($this->selectedLogId == $id) {
                $this->closeModal();
            }
        } catch (\Exception $e) {
            $this->error('Erro ao arquivar log: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function archiveSelected()
    {
        if (empty($this->selectedLogs)) {
            $this->error('Nenhum log selecionado.', position: 'toast-top');
            return;
        }

        try {
            SystemLog::whereIn('id', $this->selectedLogs)->update(['archived' => true]);
            $this->success('Logs selecionados arquivados com sucesso!', position: 'toast-top');
            $this->selectedLogs = [];
            if (in_array($this->selectedLogId, $this->selectedLogs)) {
                $this->closeModal();
            }
        } catch (\Exception $e) {
            $this->error('Erro ao arquivar logs: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedTypeFilter()
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

    public function updatedArchivedFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = SystemLog::query()
            ->when($this->search, fn($q) => $q->where('message', 'like', '%' . $this->search . '%'))
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->archivedFilter === 'active', fn($q) => $q->where('archived', false))
            ->when($this->archivedFilter === 'archived', fn($q) => $q->where('archived', true))
            ->when($this->dataNow, function ($q) {
                try {
                    $dateTime = Carbon::parse($this->dataNow, 'America/Sao_Paulo')->setTimezone('UTC');
                    return $q->whereBetween('created_at', [
                        $dateTime->copy()->subMinute(),
                        $dateTime->copy()->addMinute(),
                    ]);
                } catch (\Exception $e) {
                    $this->error('Data do Log inválida.', position: 'toast-top');
                    return $q;
                }
            })
            ->when(!$this->dataNow && $this->startDate, function ($q) {
                try {
                    return $q->whereDate('created_at', '>=', Carbon::parse($this->startDate)->startOfDay()->setTimezone('UTC'));
                } catch (\Exception $e) {
                    $this->error('Data Inicial inválida.', position: 'toast-top');
                    return $q;
                }
            })
            ->when(!$this->dataNow && $this->endDate, function ($q) {
                try {
                    return $q->whereDate('created_at', '<=', Carbon::parse($this->endDate)->endOfDay()->setTimezone('UTC'));
                } catch (\Exception $e) {
                    $this->error('Data Final inválida.', position: 'toast-top');
                    return $q;
                }
            })
            ->whereNotNull('created_at')
            ->orderBy('created_at', 'desc');

        $logs = $query->paginate($this->perPage);

        $selectedLog = $this->selectedLogId ? SystemLog::find($this->selectedLogId) : null;

        $headers = [
            ['key' => 'checkbox', 'label' => '', 'sortable' => false, 'class' => 'w-1'],
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'type', 'label' => 'Tipo'],
            ['key' => 'message', 'label' => 'Mensagem'],
            ['key' => 'created_at', 'label' => 'Data'],
        ];

        return view('livewire.process-logs-index', [
            'logs' => $logs,
            'headers' => $headers,
            'selectedLog' => $selectedLog,
            'typeOptions' => [
                ['id' => '', 'name' => 'Todos'],
                ['id' => 'info', 'name' => 'Info'],
                ['id' => 'warning', 'name' => 'Aviso'],
                ['id' => 'error', 'name' => 'Erro'],
            ],
            'archivedOptions' => [
                ['id' => 'all', 'name' => 'Todos'],
                ['id' => 'active', 'name' => 'Ativos'],
                ['id' => 'archived', 'name' => 'Arquivados'],
            ],
        ]);
    }
}