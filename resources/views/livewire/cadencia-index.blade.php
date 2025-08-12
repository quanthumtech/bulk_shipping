<div class="container mx-auto p-6">
    <x-mary-header title="Gerenciamento de Cadências" subtitle="Configure e gerencie suas sequências de mensagens" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Pesquisar cadência..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button icon="o-plus" label="Nova Cadência" class="btn-primary" link="{{ route('cadencias.create') }}" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-alert
        title="Dica: Como criar etapas para sua cadência"
        icon="o-light-bulb"
        description="{!! $descriptionCard !!}"
        class="bg-warning/10 text-warning border-warning/20 mb-4"
        dismissible
    />

    <x-mary-table
        :headers="$headers"
        :rows="$cadencias_table"
        class="bg-base-100"
        with-pagination
        per-page="perPage"
        :per-page-values="[3, 5, 10]"
    >
        @scope('header_name', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        @scope('header_description', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        @scope('header_updatet_date', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        @scope('header_active', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        @scope('actions', $row)
            <div class="flex space-x-2">
                <x-mary-button icon="o-trash" spinner class="btn-sm btn-error" wire:click="delete({{ $row->id }})" title="Excluir" />
                <x-mary-button icon="o-pencil" spinner class="btn-sm btn-warning" link="{{ route('cadencias.edit', $row->id) }}" title="Editar" />
                <x-mary-button icon="o-plus" link="{{ route('etapas.index', ['cadenciaId' => $row->id]) }}" class="btn-sm btn-primary" title="Adicionar etapas" />
            </div>
        @endscope
    </x-mary-table>
</div>