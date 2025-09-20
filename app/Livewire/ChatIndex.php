<?php

namespace App\Livewire;

use App\Services\ChatwootService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ChatIndex extends Component
{
    use WithPagination, Toast;

    public $evolutions = [];
    public $evolutionOptions = []; // New property for select options
    public $selectedEvolutionId;
    public $chats = [];
    public $selectedChat = null;
    public $messages = [];
    public $newMessage = '';
    public $search = '';

    public function mount()
    {
        $user = Auth::user();
        $this->evolutions = $user->evolutions()->where('active', true)->with('version')->get();

        // Prepare evolution options for the select component
        $this->evolutionOptions = $this->evolutions->map(function ($e) {
            return [
                'id' => $e->id,
                'name' => $e->version->name ?? 'Versão desconhecida',
            ];
        })->toArray();

        if ($this->evolutions->isNotEmpty()) {
            $this->selectedEvolutionId = $this->evolutions->first()->id;
            $this->loadChats();
        } else {
            $this->error('Nenhuma instância Evolution ativa encontrada.', position: 'toast-top');
        }
    }

    public function updatedSelectedEvolutionId()
    {
        $this->resetPage();
        $this->selectedChat = null;
        $this->messages = [];
        $this->loadChats();
    }

    public function updatedSearch()
    {
        $this->resetPage();
        $this->loadChats();
    }

    public function loadChats()
    {
        if (!$this->selectedEvolutionId) {
            $this->error('Nenhuma instância selecionada.', position: 'toast-top');
            return;
        }

        $evolution = Auth::user()->evolutions()->find($this->selectedEvolutionId);

        if (!$evolution) {
            $this->error('Instância não encontrada.', position: 'toast-top');
            return;
        }

        $service = new ChatwootService();
        $this->chats = $service->getChats($evolution->api_post, $evolution->apikey);

        $this->chats = array_filter($this->chats, function ($chat) {
            $name = $chat['name'] ?? str_replace('@c.us', '', $chat['id']);
            return empty($this->search) || stripos($name, $this->search) !== false || stripos($chat['id'], $this->search) !== false;
        });
    }

    public function selectChat($chatId)
    {
        $this->selectedChat = collect($this->chats)->firstWhere('id', $chatId);
        $this->loadMessages();
    }

    public function loadMessages()
    {
        if (!$this->selectedChat) {
            $this->error('Nenhum chat selecionado.', position: 'toast-top');
            return;
        }

        $evolution = Auth::user()->evolutions()->find($this->selectedEvolutionId);

        if (!$evolution) {
            $this->error('Instância não encontrada.', position: 'toast-top');
            return;
        }

        $phone = str_replace('@c.us', '', $this->selectedChat['id']);
        $service = new ChatwootService();
        $this->messages = $service->getMessages($evolution->api_post, $evolution->apikey, $phone);

        if (empty($this->messages)) {
            $this->info('Nenhuma mensagem encontrada para este chat.', position: 'toast-top');
        }
    }

    public function send()
    {
        if (!$this->selectedChat || empty(trim($this->newMessage))) {
            $this->error('Selecione um chat e digite uma mensagem válida.', position: 'toast-top');
            return;
        }

        $evolution = Auth::user()->evolutions()->find($this->selectedEvolutionId);

        if (!$evolution) {
            $this->error('Instância não encontrada.', position: 'toast-top');
            return;
        }

        $phone = str_replace('@c.us', '', $this->selectedChat['id']);
        $service = new ChatwootService();
        $response = $service->sendMessage(
            $phone,
            $this->newMessage,
            $evolution->api_post,
            $evolution->apikey,
            $this->selectedChat['name'] ?? null
        );

        if ($response) {
            $this->success('Mensagem enviada com sucesso!', position: 'toast-top');
            $this->newMessage = '';
            $this->loadMessages();
        } else {
            $this->error('Erro ao enviar mensagem.', position: 'toast-top');
        }
    }

    public function render()
    {
        return view('livewire.chat-index', [
            'evolutions' => $this->evolutions,
            'evolutionOptions' => $this->evolutionOptions, // Pass the precomputed options
            'chats' => $this->chats,
            'selectedChat' => $this->selectedChat,
            'messages' => $this->messages,
        ]);
    }
}