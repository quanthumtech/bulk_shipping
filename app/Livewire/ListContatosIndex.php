<?php
namespace App\Livewire;

use App\Services\ChatwootService;
use Livewire\Component;
use Mary\Traits\Toast;


class ListContatosIndex extends Component
{
    use Toast;

    public $contatos = [];
    protected $chatwootService;
    public $search = '';
    public int $page = 1; // Página atual
    public $contatosPages;
    public int $totalPages = 1; // Total de páginas
    public $nomeCompleto = '';

    public function mount(ChatwootService $chatwootService)
    {
        $this->chatwootService = $chatwootService;
        $this->contatosPages = $this->chatwootService->getContatos();
        $this->updateContatos();
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

    public function render()
    {
        if (!empty($this->search)) {
            // Usa o método de pesquisa dedicado
            $this->chatwootService = app(ChatwootService::class);
            $this->contatos = $this->chatwootService->searchContatosApi($this->search);
        } else {
            // Usa os contatos da página atual
            $this->updateContatos();
        }

        $contatos_table = collect($this->contatos)->map(function($contato, $index) {
            return [
                'id'    => $index + 1,
                'phone' => $contato['id'],
                'name'  => $contato['name'],
            ];
        });

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'phone', 'label' => 'Tel de Contato'],
            ['key' => 'name', 'label' => 'Nome do Contato'],
        ];

        $descriptionCard = 'Essa lista de contatos foi obtida do Chatwoot.
                            Em breve você poderá inserir seus contatos aqui e não
                            só enviar suas campanhas com os contatos do Chatwoot.';

        return view('livewire.list-contatos-index', [
            'headers'         => $headers,
            'contatos_table'  => $contatos_table,
            'descriptionCard' => $descriptionCard
        ]);
    }

}
