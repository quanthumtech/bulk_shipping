<?php
namespace App\Livewire;

use App\Livewire\Forms\ListContatosForm;
use App\Models\ListContatos;
use App\Models\User;
use App\Services\ChatwootService;
use Livewire\Component;
use Mary\Traits\Toast;

class ListContatosIndex extends Component
{
    use Toast;

    public ListContatosForm $form;

    public $contatos = [];
    protected $chatwootService;
    public $search = '';
    public int $page = 1; // Página atual
    public $contatosPages;
    public int $totalPages = 1; // Total de páginas
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

        // Ajuste correto do total de páginas
        $this->totalPages = count($this->contatos) > 0 ? ceil(count($this->contatos) / 10) : 1;
    }

    public function searchContatos()
    {
        if (!empty($this->search)) {
            $this->contatos = $this->chatwootService->searchContatosApi($this->search);
        } else {
            $this->updateContatos(); // Volta para a listagem normal paginada
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
            $this->form->store();
            $this->success('Contato cadastrado com sucesso!', position: 'toast-top');

            // Fechar o modal
            $this->contactModal = false;

        } catch (\Exception $e) {
            $this->error('Erro ao salvar o grupo: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function render()
    {
        /*if (!empty($this->search)) {
            $this->chatwootService = app(ChatwootService::class);
            $this->contatos = $this->chatwootService->searchContatosApi($this->search);
        } else {
            $this->updateContatos();
        }

        $contatos_table = collect($this->contatos)->map(function ($contato, $index) {
            $phone = $contato['id'] ?? null;
            if ($phone && preg_match('/^\+55\d{10,11}$/', $phone)) {
                $displayPhone = $phone;
            } else {
                $displayPhone = '';
            }
            return [
                'id'    => $index + 1,
                'phone' => $displayPhone,
                'name'  => $contato['name'] ?? '',
            ];
        })->filter(function($contato) {
            return !empty($contato['phone']);
        });

        $hasValidPhone = $contatos_table->contains(function ($contato) {
            return !empty($contato['phone']);
        });

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
        ];

        if ($hasValidPhone) {
            $headers[] = ['key' => 'phone', 'label' => 'Tel de Contato'];
        }

        $headers[] = ['key' => 'name', 'label' => 'Nome do Contato'];
        */
        $userId = User::find(auth()->id());

        $contatos_table = ListContatos::where('chatwoot_id', $userId->chatwoot_accoumts)->where(function ($query) {
            $query->where('phone_number', 'like', '%' . $this->search . '%')
                ->orWhere('contact_name', 'like', '%' . $this->search . '%');
            })->orderBy('created_at', 'desc')->paginate($this->perPage);

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
            'headers'         => $headers,
            'contatos_table'  => $contatos_table,
            'descriptionCard' => $descriptionCard
        ]);
    }

}
