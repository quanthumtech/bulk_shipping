<?php

namespace App\Livewire;

use App\Livewire\Forms\GroupSendForm;
use App\Models\GroupSend;
use App\Services\ChatwootService;
use Livewire\Component;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;

class GroupSendIndex extends Component
{
    use WithFileUploads, Toast;

    public GroupSendForm $form;

    public $title = '';

    public bool $groupModal = false;

    public bool $editMode = false;

    protected $chatwootService;

    public $contatos;

    public $search = '';

    public $pages = 21; //pensar melhor

    public function mount(ChatwootService $chatwootService)
    {
        $this->chatwootService = $chatwootService;

        $this->contatos = $this->chatwootService->getContatos($this->pages);
    }

    public function showModal()
    {
        $this->form->reset();
        $this->editMode = false;
        $this->groupModal = true;
        $this->title = 'Criar Grupo';
    }

    public function save()
    {
        try {
            // Filtrar os contatos selecionados pelo usuário
            $selectedContacts = collect($this->form->phone_number)->map(function ($contactId) {
                return collect($this->contatos)->firstWhere('id', $contactId);
            })->filter();

            // Separar os dados em dois arrays: números de telefone e nomes
            $phoneNumbers = $selectedContacts->pluck('id')->toArray();
            $contactNames = $selectedContacts->pluck('name')->toArray();

            // Atualizar o formulário com os valores processados
            $this->form->phone_number = $phoneNumbers;
            $this->form->contact_name = $contactNames;

            // Salvar no banco (diferenciar entre criação e edição)
            if ($this->editMode) {
                $this->form->update();
                $this->editMode = false;
                $this->success('Grupo atualizado com sucesso!', position: 'toast-top');
            } else {
                $this->form->store();
                $this->success('Grupo cadastrado com sucesso!', position: 'toast-top');
            }

            // Fechar o modal
            $this->groupModal = false;

        } catch (\Exception $e) {
            $this->error('Erro ao salvar o grupo: ' . $e->getMessage(), position: 'toast-top');
        }
    }


    public function edit($id)
    {
        $group = GroupSend::find($id);

        if ($group) {
            $this->form->setNote($group);
            $this->editMode = true;
            $this->groupModal = true;
            $this->title = 'Editar Grupo';
        } else {
            $this->info('Grupo não encontrado.', position: 'toast-top');
        }
    }

    public function delete($id)
    {
        GroupSend::find($id)->delete();
        $this->info('Grupo excluído com sucesso.', position: 'toast-top');
    }

    public function render()
    {
        $userId = auth()->id();

        $groups = GroupSend::where('user_id', $userId)
                    ->where(function($query) {
                        $query->where('title', 'like', '%' . $this->search . '%')
                            ->orWhere('sub_title', 'like', '%' . $this->search . '%')
                            ->orWhere('description', 'like', '%' . $this->search . '%')
                            ->orWhere('phone_number', 'like', '%' . $this->search . '%');
                    })
                    ->where('active', 1)
                    ->get();

        $descriptionCard = 'Para começar, crie um grupo onde você
                            poderá organizar seus contatos. Isso
                            facilitará o envio de mensagens das suas
                            campanhas, garantindo que cheguem ao público
                            certo de forma mais eficiente.';

        return view('livewire.group-send-index', [
            'groups'          => $groups,
            'descriptionCard' => $descriptionCard,
            'contatos'        => $this->contatos
        ]);
    }
}
