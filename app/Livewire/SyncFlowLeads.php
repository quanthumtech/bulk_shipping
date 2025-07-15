<?php

namespace App\Livewire;

use App\Livewire\Forms\SyncFlowLeadsForm;
use App\Models\Cadencias;
use App\Models\SyncFlowLeads as ModelsSyncFlowLeads;
use App\Models\SystemNotification;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncFlowLeads extends Component
{
    use WithPagination, Toast;

    public SyncFlowLeadsForm $form;
    public $cadenciaId;
    public $title = '';
    public bool $syncLeadsModal = false;
    public bool $cadenceModal = false;
    public bool $editMode = false;
    public string $search = '';
    public int $perPage = 6;
    public bool $showFilterDrawer = false;
    public ?string $contactNameFilter = null;
    public ?string $contactNumberFilter = null;
    public ?string $contactEmailFilter = null;
    public ?string $estagioFilter = null;
    public ?string $situacaoContatoFilter = null;
    public ?string $cadenciaFilter = null;
    public ?bool $isFromWebhookFilter = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public array $show = []; // Array to track collapse state for each lead

    protected $queryString = [
        'search',
        'contactNameFilter',
        'contactNumberFilter',
        'contactEmailFilter',
        'estagioFilter',
        'situacaoContatoFilter',
        'cadenciaFilter',
        'isFromWebhookFilter',
        'startDate',
        'endDate',
    ];

    public function mount()
    {
        // Initialize $show array for each lead (will be populated in render)
        $this->show = [];
    }

    public function toggleCollapse($leadId)
    {
        $this->show[$leadId] = !($this->show[$leadId] ?? false);
        Log::info("Collapse for lead ID '{$leadId}' toggled: " . ($this->show[$leadId] ? 'Aberto' : 'Fechado'));
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingContactNameFilter()
    {
        $this->resetPage();
    }

    public function updatingContactNumberFilter()
    {
        $this->resetPage();
    }

    public function updatingContactEmailFilter()
    {
        $this->resetPage();
    }

    public function updatingEstagioFilter()
    {
        $this->resetPage();
    }

    public function updatingSituacaoContatoFilter()
    {
        $this->resetPage();
    }

    public function updatingCadenciaFilter()
    {
        $this->resetPage();
    }

    public function updatingIsFromWebhookFilter()
    {
        $this->resetPage();
    }

    public function updatingStartDate()
    {
        $this->resetPage();
    }

    public function updatingEndDate()
    {
        $this->resetPage();
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
        $this->contactNameFilter = null;
        $this->contactNumberFilter = null;
        $this->contactEmailFilter = null;
        $this->estagioFilter = null;
        $this->situacaoContatoFilter = null;
        $this->cadenciaFilter = null;
        $this->isFromWebhookFilter = null;
        $this->startDate = null;
        $this->endDate = null;
        $this->resetPage();
        $this->success('Filtros resetados!', position: 'toast-top');
    }

    public function applyFilters()
    {
        $this->validate([
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
        ]);

        $this->resetPage();
        $this->showFilterDrawer = false;
        $this->success('Filtros aplicados!', position: 'toast-top');
    }

    public function showModal()
    {
        $this->form->reset();
        $this->syncLeadsModal = true;
        $this->title = 'Adicionar Lead';
    }

    public function cadenceModal()
    {
        $this->form->reset();
        $this->syncLeadsModal = true;
        $this->form->cadenciaId = $this->cadenciaId;
        $this->title = 'Atribuir Cadência';
    }

    public function save()
    {
        try {
            if ($this->editMode) {
                $this->form->update();
                $this->editMode = false;
                $this->success('Lead atualizado com sucesso!', position: 'toast-top');
            } else {
                $this->form->store();
                SystemNotification::create([
                    'user_id' => auth()->user()->id,
                    'title'   => 'Lead Cadastrado Manual',
                    'message' => 'Um novo lead foi cadastrado manualmente, nome: ' . $this->form->contact_name . ', número: ' . $this->form->contact_number,
                    'read'    => false
                ]);
                $this->success('Lead cadastrado com sucesso!', position: 'toast-top');
            }
            $this->syncLeadsModal = false;
        } catch (\Exception $e) {
            $this->error('Erro ao salvar a Etapa.', position: 'toast-top');
            Log::error('Erro ao salvar lead: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $syncLeadsEdit = ModelsSyncFlowLeads::find($id);

        if ($syncLeadsEdit) {
            $this->form->setSyncFlowLeads($syncLeadsEdit);
            $this->editMode = true;
            $this->syncLeadsModal = true;
            $this->title = 'Editar Lead';
        } else {
            $this->info('Etapa não encontrada.', position: 'toast-top');
        }
    }

    public function cadence($id)
    {
        $syncLeadsCadence = ModelsSyncFlowLeads::find($id);

        if ($syncLeadsCadence) {
            $this->form->setSyncFlowLeads($syncLeadsCadence);
            $this->cadenceModal = true;
            $this->form->cadenciaId = $this->cadenciaId;
            $this->title = 'Atribuir Cadência';
        } else {
            $this->info('Etapa não encontrada.', position: 'toast-top');
        }
    }

    public function cadenceSave()
    {
        try {
            $this->form->update();
            SystemNotification::create([
                'user_id' => auth()->user()->id,
                'title'   => 'Cadência Atribuída',
                'message' => 'Uma cadência foi atribuída manualmente ao lead: ' . $this->form->contact_name . ', número: ' . $this->form->contact_number,
                'read'    => false
            ]);
            $this->success('Cadência atribuida com sucesso!', position: 'toast-top');
            $this->cadenceModal = false;
        } catch (\Exception $e) {
            $this->error('Erro ao salvar a Cadência.', position: 'toast-top');
            Log::error('Erro ao salvar a Cadência: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            ModelsSyncFlowLeads::findOrFail($id)->delete();
            $this->success('Lead deletado com sucesso!', position: 'toast-top');
            $this->resetPage();
        } catch (\Exception $e) {
            $this->error('Erro ao deletar lead.', position: 'toast-top');
            Log::error('Erro ao deletar lead: ' . $e->getMessage());
        }
    }

    public function getCadenciaOptionsProperty()
    {
        return array_merge(
            [['id' => '', 'name' => 'Todas']],
            Cadencias::where('user_id', auth()->user()->id)
                ->get()
                ->map(function ($cadencia) {
                    return [
                        'id' => $cadencia->id,
                        'name' => $cadencia->name,
                    ];
                })->toArray()
        );
    }

    public function getEstagioOptionsProperty()
    {
        $estagios = ModelsSyncFlowLeads::select('estagio')
            ->distinct()
            ->whereNotNull('estagio')
            ->where('estagio', '!=', 'Não informado')
            ->pluck('estagio')
            ->map(function ($estagio) {
                return [
                    'id' => $estagio,
                    'name' => $estagio,
                ];
            })->toArray();

        return array_merge(
            [['id' => '', 'name' => 'Todos']],
            $estagios
        );
    }

    public function getSituacaoContatoOptionsProperty()
    {
        $situacoes = ModelsSyncFlowLeads::select('situacao_contato')
            ->distinct()
            ->whereNotNull('situacao_contato')
            ->where('situacao_contato', '!=', 'Não informado')
            ->pluck('situacao_contato')
            ->map(function ($situacao) {
                return [
                    'id' => $situacao,
                    'name' => $situacao,
                ];
            })->toArray();

        return array_merge(
            [['id' => '', 'name' => 'Todas']],
            $situacoes
        );
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user || !isset($user->chatwoot_accoumts)) {
            Log::warning('User or chatwoot_accoumts is null for user ID: ' . ($user ? $user->id : 'null'));
            $this->error('Conta Chatwoot não configurada.', position: 'toast-top');
            return view('livewire.sync-flow-leads', [
                'syncFlowLeads' => collect()->paginate($this->perPage),
                'cadencias' => collect([['id' => '', 'name' => 'Selecione uma cadência']]),
                'estagioOptions' => $this->estagioOptions,
                'situacaoContatoOptions' => $this->situacaoContatoOptions,
                'cadenciaOptions' => $this->cadenciaOptions,
            ]);
        }

        $syncFlowLeads = ModelsSyncFlowLeads::with(['cadencia', 'chatwootConversations'])
            ->where('chatwoot_accoumts', $user->chatwoot_accoumts)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('contact_name', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_number', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_number_empresa', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_email', 'like', '%' . $this->search . '%')
                      ->orWhere('estagio', 'like', '%' . $this->search . '%')
                      ->orWhere('situacao_contato', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->contactNameFilter, fn($q) => $q->where('contact_name', 'like', '%' . $this->contactNameFilter . '%'))
            ->when($this->contactNumberFilter, fn($q) => $q->where('contact_number', 'like', '%' . $this->contactNumberFilter . '%'))
            ->when($this->contactEmailFilter, fn($q) => $q->where('contact_email', 'like', '%' . $this->contactEmailFilter . '%'))
            ->when($this->estagioFilter, fn($q) => $q->where('estagio', $this->estagioFilter))
            ->when($this->situacaoContatoFilter, fn($q) => $q->where('situacao_contato', $this->situacaoContatoFilter))
            ->when($this->cadenciaFilter, fn($q) => $q->where('cadencia_id', $this->cadenciaFilter))
            ->when($this->isFromWebhookFilter !== null, fn($q) => $q->where(function ($query) {
                if ($this->isFromWebhookFilter) {
                    $query->whereNotNull('id_card')->where('id_card', '!=', 'Não fornecido');
                } else {
                    $query->where(function ($q) {
                        $q->whereNull('id_card')->orWhere('id_card', 'Não fornecido');
                    });
                }
            }))
            ->when($this->startDate, fn($q) => $q->whereDate('created_at', '>=', Carbon::parse($this->startDate)->startOfDay()->setTimezone('UTC')))
            ->when($this->endDate, fn($q) => $q->whereDate('created_at', '<=', Carbon::parse($this->endDate)->endOfDay()->setTimezone('UTC')))
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        // Initialize collapse state for each lead
        foreach ($syncFlowLeads as $lead) {
            if (!isset($this->show[$lead->id])) {
                $this->show[$lead->id] = false;
            }
        }

        $cadencias = collect([['id' => '', 'name' => 'Selecione uma cadência']])
            ->concat(Cadencias::where('user_id', $user->id)->get());

        return view('livewire.sync-flow-leads', [
            'syncFlowLeads' => $syncFlowLeads,
            'cadencias' => $cadencias,
            'estagioOptions' => $this->estagioOptions,
            'situacaoContatoOptions' => $this->situacaoContatoOptions,
            'cadenciaOptions' => $this->cadenciaOptions,
        ]);
    }
}