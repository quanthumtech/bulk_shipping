<?php
namespace App\Livewire;

use App\Services\ChatwootService;
use Livewire\Component;

class ListContatosIndex extends Component
{
    public $contatos = [];
    protected $chatwootService;
    public $search = '';
    public int $page = 1; // Página atual
    public $contatosPages;
    public int $totalPages = 1; // Total de páginas

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

        // Se houver uma pesquisa, filtra os contatos
        if ($this->search) {
            $this->contatos = $this->searchContatos($this->search);
        }

        // Ajuste correto do total de páginas
        $this->totalPages = count($this->contatos) > 0 ? ceil(count($this->contatos) / 10) : 1;
    }

    public function searchContatos($searchTerm)
    {
        return collect($this->contatosPages)->filter(function ($contato) use ($searchTerm) {
            return strpos(strtolower($contato['name']), strtolower($searchTerm)) !== false ||
                strpos(strtolower($contato['id']), strtolower($searchTerm)) !== false;
        })->values()->all();
    }

    public function nextPage()
    {
        //if ($this->page < $this->totalPages) {
            $this->page++;
            $this->updateContatos();
        //}
    }

    public function previousPage()
    {
        //if ($this->page > 1) {
            $this->page--;
            $this->updateContatos();
        //}
    }

    public function render()
    {
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
