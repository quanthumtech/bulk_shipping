<?php

namespace App\Livewire;

use App\Livewire\Forms\SyncFlowLeadsForm;
use App\Models\Cadencias;
use App\Models\SyncFlowLeads as ModelsSyncFlowLeads;
use App\Models\SystemNotification;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class SyncFlowLeads extends Component
{
    use WithPagination, Toast;

    public SyncFlowLeadsForm $form;
    public $cadenciaId;
    public $title = '';
    public bool $syncLeadsModal = false;
    public bool $cadenceModal = false;
    public bool $historyModal = false; // Novo modal para histórico
    public bool $editMode = false;
    public string $search = '';
    public int $perPage = 6;
    public $selectedLead = null; // Lead selecionado para o histórico
    public $conversationHistory = []; // Histórico de mensagens

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
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
        $this->title = 'Artelar Cadência';
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
                // Notificação lead cadastrado
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
            $this->title = 'Artelar Cadência';
        } else {
            $this->info('Etapa não encontrada.', position: 'toast-top');
        }
    }

    public function cadenceSave()
    {
        try {
            $this->form->update();

            // Notificação cadência atribuída manualmente
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
        }
    }

    public function delete($id)
    {
        ModelsSyncFlowLeads::findOrFail($id)->delete();
        $this->success('Lead deletado com sucesso!', position: 'toast-top');
        $this->resetPage();
    }

    // Novo método para visualizar o histórico de conversas
    public function viewHistory($id)
    {
        $lead = ModelsSyncFlowLeads::with('chatwootConversations.messages')->find($id);

        if ($lead) {
            $this->selectedLead = $lead;
            // Carregar mensagens de todas as conversas do lead
            $this->conversationHistory = $lead->chatwootConversations->flatMap(function ($conversation) {
                return $conversation->messages->map(function ($message) {
                    return [
                        'content' => $message->content,
                        'message_id' => $message->message_id,
                        'created_at' => $message->created_at->format('d/m/Y H:i'),
                    ];
                });
            })->sortBy('created_at')->toArray();

            $this->historyModal = true;
        } else {
            $this->info('Lead não encontrado.', position: 'toast-top');
        }
    }

    public function render()
    {
        $user = auth()->user();

        $syncFlowLeads = ModelsSyncFlowLeads::with(['cadencia', 'chatwootConversations'])
            ->where('chatwoot_accoumts', $user->chatwoot_accoumts)
            ->when($this->search, function ($query) {
                $query->where('contact_name', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_number', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_number_empresa', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_email', 'like', '%' . $this->search . '%')
                      ->orWhere('estagio', 'like', '%' . $this->search . '%')
                      ->orWhere('situacao_contato', 'like', '%' . $this->search . '%');
            })
            ->paginate($this->perPage);

        $cadencias = collect([['id' => '', 'name' => 'Selecione uma cadência']])
            ->concat(Cadencias::where('user_id', $user->id)->get());

        return view('livewire.sync-flow-leads', [
            'syncFlowLeads' => $syncFlowLeads,
            'cadencias' => $cadencias,
        ]);
    }
}
