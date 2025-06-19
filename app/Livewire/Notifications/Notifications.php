<?php

namespace App\Livewire\Notifications;

use App\Models\SystemNotification;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Notifications extends Component
{
    public $showDrawer = false;
    public $unreadNotificationsCount = 0;
    public $notifications = [];

    public function mount()
    {
        $this->loadNotifications();
    }

    public function loadNotifications()
    {
        if (Auth::check()) {
            $this->notifications = SystemNotification::where('user_id', Auth::id())
                ->latest()
                ->take(10)
                ->get();
            $this->unreadNotificationsCount = SystemNotification::where('user_id', Auth::id())
                ->where('read', false)
                ->count();
        } else {
            $this->notifications = [];
            $this->unreadNotificationsCount = 0;
        }
    }

    public function openNotifications()
    {
        $this->showDrawer = true;
        $this->loadNotifications();
    }

    public function markAsRead($notificationId)
    {
        if (Auth::check()) {
            $notification = SystemNotification::where('user_id', Auth::id())->find($notificationId);
            if ($notification) {
                $notification->markAsRead();
                $this->loadNotifications();
            }
        }
    }

    public function render()
    {
        return view('livewire.notifications.notifications',
            [
                'notifications' => $this->notifications,
                'unreadNotificationsCount' => $this->unreadNotificationsCount,
            ]
        );
    }
}
