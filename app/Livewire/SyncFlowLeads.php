<?php

namespace App\Livewire;

use App\Livewire\Forms\SyncFlowLeadsForm;
use App\Models\Cadencias;
use App\Models\SyncFlowLeads as ModelsSyncFlowLeads;
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
    public bool $editMode = false;
    public string $search = ''; // Adicionando a pesquisa
    public int $perPage = 6;

    protected $queryString = ['search']; // Mantém a pesquisa na URL

    public function updatingSearch()
    {
        $this->resetPage(); // Reseta a paginação ao alterar a busca
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
        $this->resetPage(); // Atualiza a paginação após deletar
    }

    public function render()
    {
        $user = auth()->user();

        $syncFlowLeads = ModelsSyncFlowLeads::with('cadencia')
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
            'syncFlowLeads'           => $syncFlowLeads,
            'cadencias'               => $cadencias,
        ]);
    }

}
