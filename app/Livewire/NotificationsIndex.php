<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\SystemNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationsIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $expanded = []; // Propriedade para gerenciar expansão
    public int $perPage = 10;

    protected $headers = [
        ['key' => 'title', 'label' => 'Título'],
        ['key' => 'message', 'label' => 'Mensagem'],
        ['key' => 'created_at', 'label' => 'Criado em'],
        ['key' => 'read', 'label' => 'Status'],
        ['key' => 'actions', 'label' => 'Ações'],
    ];

    public function mount()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
        $this->expanded = []; // Reseta expansão ao pesquisar
    }

    public function updatedPerPage()
    {
        $this->resetPage();
        $this->expanded = []; // Reseta expansão ao mudar itens por página
    }

    public function markAsRead($notificationId)
    {
        if (Auth::check()) {
            $notification = SystemNotification::where('user_id', Auth::id())->find($notificationId);
            if ($notification) {
                $notification->markAsRead();
                Log::info("Notificação marcada como lida: ID {$notificationId}, Usuário ID " . Auth::id());
            }
        }
    }

    public function deleteNotification($notificationId)
    {
        if (Auth::check()) {
            $notification = SystemNotification::where('user_id', Auth::id())->find($notificationId);
            if ($notification) {
                $notification->delete();
                Log::info("Notificação excluída: ID {$notificationId}, Usuário ID " . Auth::id());
                $this->expanded = array_diff($this->expanded, [$notificationId]); // Remove da expansão
            } else {
                Log::warning("Tentativa de excluir notificação inexistente ou não autorizada: ID {$notificationId}, Usuário ID " . Auth::id());
            }
        }
    }

    public function render()
    {
        $notifications = SystemNotification::where('user_id', Auth::id())
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                      ->orWhere('message', 'like', '%' . $this->search . '%');
                });
            })
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.notifications-index', [
            'notifications' => $notifications,
            'headers' => $this->headers,
        ]);
    }
}
