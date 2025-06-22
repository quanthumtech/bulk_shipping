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
    public $expanded = [];
    public int $perPage = 10;
    public bool $showDrawer = false; // Controla a visibilidade do drawer
    public string $filterStatus = 'all'; // Filtro de status: 'all', 'read', 'unread'
    public $filterDate = null; // Filtro de data (opcional)

    protected $headers = [
        ['key' => 'title', 'label' => 'Título'],
        ['key' => 'message', 'label' => 'Mensagem'],
        ['key' => 'created_at', 'label' => 'Criado em'],
        ['key' => 'read', 'label' => 'Status'],
    ];

    public function mount()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
        $this->expanded = [];
    }

    public function updatedPerPage()
    {
        $this->resetPage();
        $this->expanded = [];
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
        $this->expanded = [];
    }

    public function updatedFilterDate()
    {
        $this->resetPage();
        $this->expanded = [];
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
                $this->expanded = array_diff($this->expanded, [$notificationId]);
            } else {
                Log::warning("Tentativa de excluir notificação inexistente ou não autorizada: ID {$notificationId}, Usuário ID " . Auth::id());
            }
        }
    }

    public function render()
    {
        $query = SystemNotification::where('user_id', Auth::id())
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                      ->orWhere('message', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterStatus !== 'all', function ($query) {
                $query->where('read', $this->filterStatus === 'read');
            })
            ->when($this->filterDate, function ($query) {
                $query->whereDate('created_at', $this->filterDate);
            });

        $notifications = $query->latest()->paginate($this->perPage);

        return view('livewire.notifications-index', [
            'notifications' => $notifications,
            'headers' => $this->headers,
        ]);
    }
}
