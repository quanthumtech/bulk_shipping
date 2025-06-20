<style>
    table.notifications-table {
        @apply !static;
    }
    table.notifications-table details.dropdown {
        @apply !static;
    }
</style>

<div>
    <x-mary-header title="Notificações" subtitle="Todas as suas notificações" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-magnifying-glass" wire:model.live="search" placeholder="Pesquisar notificações..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button icon="o-funnel" title="Filtrar" />
            <x-mary-button label="Voltar" link="/dashboard" class="btn-primary" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-table
        :headers="$headers"
        :rows="$notifications"
        striped
        class="notifications-table bg-base-100"
        with-pagination per-page="perPage"
        :per-page-values="[3, 5, 10]"
        expandable
        wire:model="expanded"
        expandable-key="id"
    >
        {{-- Cabeçalho: Título --}}
        @scope('header_title', $header)
            <h3 class="text-lg font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope

        {{-- Cabeçalho: Mensagem --}}
        @scope('header_message', $header)
            <h3 class="text-lg font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope

        {{-- Cabeçalho: Criado em --}}
        @scope('header_created_at', $header)
            <h3 class="text-lg font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope

        {{-- Cabeçalho: Status --}}
        @scope('header_read', $header)
            <h3 class="text-lg font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope

        {{-- Célula: Mensagem --}}
        @scope('cell_message', $notification)
            <span class="text-sm text-gray-600">{{ Str::limit($notification->message, 50) }}</span>
        @endscope

        {{-- Célula: Criado em --}}
        @scope('cell_created_at', $notification)
            <span>{{ $notification->created_at->format('d/m/Y H:i') }}</span>
        @endscope

        {{-- Célula: Status --}}
        @scope('cell_read', $notification)
            <x-mary-badge :value="$notification->read ? 'Lida' : 'Não lida'" :class="$notification->read ? 'badge-success' : 'badge-warning'" />
        @endscope

        {{-- Ações --}}
        @scope('actions', $notification)
            <div class="flex space-x-2">
                @if(!$notification->read)
                    <x-mary-button
                        icon="o-check"
                        wire:click="markAsRead({{ $notification->id }})"
                        spinner
                        class="btn-sm btn-ghost"
                        title="Marcar como lida"
                    />
                @endif
                <x-mary-button
                    icon="o-trash"
                    wire:click="deleteNotification({{ $notification->id }})"
                    spinner
                    class="btn-sm btn-error"
                    title="Excluir"
                />
            </div>
        @endscope

        {{-- Expansão --}}
        @scope('expansion', $notification)
            <div class="bg-base-200 p-4">
                <h4 class="font-semibold text-lg">{{ $notification->title }}</h4>
                <p class="text-gray-600 mt-2">{{ $notification->message }}</p>
                <p class="text-sm text-gray-400 mt-1">Criado em: {{ $notification->created_at->format('d/m/Y H:i:s') }}</p>
                <p class="text-sm text-gray-400">Status: {{ $notification->read ? 'Lida' : 'Não lida' }}</p>
            </div>
        @endscope
    </x-mary-table>
</div>
