<?php

namespace App\Livewire;

use App\Events\GenericAudit;
use App\Livewire\Forms\ListContatosForm;
use App\Models\ListContatos;
use App\Models\User;
use App\Services\ChatwootService;
use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Mary\Traits\Toast;

class ListContatosIndex extends Component
{
    use Toast;

    public ListContatosForm $form;

    public $contatos = [];
    protected $chatwootService;
    public $search = '';
    public int $page = 1;
    public $contatosPages;
    public int $totalPages = 1;
    public $nomeCompleto = '';
    public bool $contactModal = false;
    public $title = '';
    public int $perPage = 5;

    public function mount(ChatwootService $chatwootService)
    {
        $this->chatwootService = $chatwootService;
        $this->contatosPages = $this->chatwootService->getContatos();
        $this->iniciarSincronizacao();
        $this->updateContatos();
    }

    public function iniciarSincronizacao()
    {
        $this->chatwootService = app(ChatwootService::class);
        $this->chatwootService->syncContatos();
        $this->success('Sincronização finalizada!', position: 'toast-top', timeout: 6000, description: 'Os contatos foram sincronizados com sucesso.');
    }

    public function updateContatos()
    {
        $this->chatwootService = app(ChatwootService::class);
        if (!$this->chatwootService) {
            throw new \Exception("ChatwootService não foi injetado corretamente.");
        }

        $result = $this->chatwootService->getContatos($this->page);
        $this->contatos = is_array($result) ? $result : [];
        $this->totalPages = count($this->contatos) > 0 ? ceil(count($this->contatos) / 10) : 1;
    }

    public function searchContatos()
    {
        if (!empty($this->search)) {
            $this->contatos = $this->chatwootService->searchContatosApi($this->search);
        } else {
            $this->updateContatos();
        }
    }

    public function nextPage()
    {
        $this->page++;
        $this->updateContatos();
    }

    public function previousPage()
    {
        $this->page--;
        $this->updateContatos();
    }

    public function showModal()
    {
        $this->form->reset();
        $this->contactModal = true;
        $this->title = 'Adicionar Contato';
    }

    public function save()
    {
        try {
            // Capture form data before store()
            $formData = [
                'name' => $this->form->contact_name ?? 'N/A',
                'phone_number' => $this->form->phone_number ?? 'N/A',
            ];

            // Normalize phone_number for consistency
            $normalizedPhone = preg_replace('/\D/', '', $formData['phone_number']);
            if (strlen($normalizedPhone) == 10 || strlen($normalizedPhone) == 11) {
                $normalizedPhone = '+55' . $normalizedPhone;
            }

            // Call store() to create the contact
            $contact = $this->form->store();

            // Fallback to latest contact if store() doesn't return a model
            if (!$contact instanceof ListContatos) {
                $contact = ListContatos::where('phone_number', $normalizedPhone)
                    ->where('chatwoot_id', auth()->user()->chatwoot_accoumts)
                    ->latest()
                    ->first();
            }

            // Ensure contact is valid
            if (!$contact) {
                throw new \Exception('No contact found after store(). Check ListContatos table or ChatwootService integration.');
            }

            // Notify success
            $this->success('Contato cadastrado com sucesso!', position: 'toast-top');

            // Dispatch GenericAudit event
            $auditData = [
                'message' => 'Contato adicionado',
                'contact_id' => $contact->id,
                'contact_name' => $formData['name'],
                'contact_phone' => $formData['phone_number'],
                'auditable_type' => get_class($contact),
                'auditable_id' => $contact->id,
            ];
            Event::dispatch(new GenericAudit('add.contact', $auditData));

            // Close the modal
            $this->contactModal = false;
        } catch (\Exception $e) {
            $this->error('Erro ao cadastrar contato: ' . $e->getMessage(), position: 'toast-top');

            // Dispatch GenericAudit for failure
            $auditData = [
                'message' => 'Falha ao adicionar contato',
                'error' => $e->getMessage(),
                'form_data' => $formData,
                'auditable_type' => ListContatos::class,
                'auditable_id' => 0,
            ];
            Event::dispatch(new GenericAudit('add.contact_failed', $auditData));
        }
    }

    public function render()
    {
        $userId = User::find(auth()->id());
        $contatos_table = ListContatos::where('chatwoot_id', $userId->chatwoot_accoumts)
            ->where(function ($query) {
                $query->where('phone_number', 'like', '%' . $this->search . '%')
                    ->orWhere('contact_name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'phone_number', 'label' => 'Tel de Contato'],
            ['key' => 'contact_name', 'label' => 'Remetente'],
        ];

        $descriptionCard = 'Essa lista de contatos foi obtida do Chatwoot.
                            Esses contatos são sincronizados automaticamente, se tiver muitos contatos,
                            pode demorar um pouco.
                            Em breve você poderá inserir seus contatos aqui e não
                            só enviar suas campanhas com os contatos do Chatwoot.';

        return view('livewire.list-contatos-index', [
            'headers' => $headers,
            'contatos_table' => $contatos_table,
            'descriptionCard' => $descriptionCard
        ]);
    }
}
