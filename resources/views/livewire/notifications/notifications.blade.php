<div>
    {{-- Botão de Notificações --}}
    <div wire:click="openNotifications" class="relative">
        <x-mary-button icon="o-bell" class="btn-circle relative">
            @if($unreadNotificationsCount > 0)
                <x-mary-badge value="{{ $unreadNotificationsCount }}" class="badge-error absolute -right-2 -top-2" />
            @endif
        </x-mary-button>
    </div>

    {{-- Drawer para Notificações --}}
    <x-mary-drawer
        wire:model="showDrawer"
        title="Notificações"
        subtitle="Visualize suas notificações"
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
        right>

        <div class="p-4">
            @if(empty($notifications))
                <p class="text-gray-500">Nenhuma notificação disponível.</p>
            @else
            <ul class="space-y-3">
                @foreach($notifications as $notification)
                    <li class="p-3 rounded-lg {{ $notification->read ? 'bg-base-100' : 'bg-base-200' }}">
                        <div>
                            <p class="font-semibold">{{ $notification->title }}</p>
                            <p class="text-sm text-gray-600">{{ $notification->message }}</p>
                            <p class="text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex justify-end mt-2 space-x-2">
                            @if(!$notification->read)
                            <x-mary-button
                                label="Marcar como lida"
                                class="btn-sm btn-ghost"
                                wire:click="markAsRead({{ $notification->id }})" />
                            @endif
                            <x-mary-button
                                icon="o-trash"
                                class="btn-sm btn-ghost text-red-500 hover:text-red-700"
                                title="Excluir notificação"
                                wire:click="deleteNotification({{ $notification->id }})" />
                        </div>
                    </li>
                @endforeach
            </ul>
            @endif
            {{-- INFO: Ver todas as notificações --}}
            <div class="mt-4">
                <x-mary-button
                    label="Ver todas as notificações"
                    class="w-full"
                    link="/notifications-index" />
            </div>
        </div>
    </x-mary-drawer>
</div>
