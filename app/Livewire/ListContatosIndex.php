<?php

namespace App\Livewire;

use App\Services\ChatwootService;
use Livewire\Component;

class ListContatosIndex extends Component
{

    public $contatos;

    protected $chatwootService;

    public $search = '';

    public int $perPage = 3;

    public $page = 1;

    public function mount(ChatwootService $chatwootService)
    {
        $this->chatwootService = $chatwootService;

        $this->contatos = $this->chatwootService->getContatos();
    }

    public function render()
    {

        $filteredContatos = collect($this->contatos)->filter(function($contato) {
            return strpos($contato['name'], $this->search) !== false || strpos($contato['id'], $this->search) !== false;
        });

        $contatos_table = $filteredContatos->map(function($contato, $index) {
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

        $descriptionCard = 'Essa lista de contatos foi obtida do chatwoot.
                            Em breve você poderá inserir seus contatos aqui e não
                            só enviar suas campanhas com os contatos do chatwoot.';

        return view('livewire.list-contatos-index', [
            'headers'         => $headers,
            'contatos_table'  => $contatos_table,
            'descriptionCard' => $descriptionCard
        ]);
    }
}
