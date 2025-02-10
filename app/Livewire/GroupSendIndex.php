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

    public $searchContatos = '';

    public array $phone_number = [];

    public bool $groupModal = false;

    public bool $editMode = false;

    protected $chatwootService;

    public $contatos;

    public $search = '';

    public $pages = 21; //pensar melhor

    public int $totalPages = 1; // Total de páginas

    public $selectedContact;


    public function mount(ChatwootService $chatwootService)
    {
        $this->chatwootService = $chatwootService;

        $this->contatos = $this->chatwootService->getContatos($this->pages);
    }

    public function updateContatos()
    {
        $this->chatwootService = app(ChatwootService::class);
        if (!$this->chatwootService) {
            throw new \Exception("ChatwootService não foi injetado corretamente.");
        }

        $result = $this->chatwootService->getContatos($this->pages);
        $this->contatos = is_array($result) ? $result : [];

        // Ajuste correto do total de páginas
        $this->totalPages = count($this->contatos) > 0 ? ceil(count($this->contatos) / 10) : 1;
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

    public function updatedUserSearchableId($value)
    {
        $existing = collect($this->form->phone_number);
        $selected = collect($this->contatos)->firstWhere('id', $value);

        if ($selected && !$existing->contains($value)) {
            $this->form->phone_number[] = $value;
        }
    }

    public function searchContatosf($value = '')
    {
        $this->chatwootService = app(ChatwootService::class);
        $result = $this->chatwootService->searchContatosApi($value);

        // Mantém os contatos já selecionados no campo `phone_number`
        $selectedContacts = collect($this->form->phone_number)->map(function ($contactId) {
            return collect($this->contatos)->firstWhere('id', $contactId);
        })->filter()->toArray();

        // Garante que os contatos selecionados não sejam removidos
        $this->contatos = array_merge($selectedContacts, $result);
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
