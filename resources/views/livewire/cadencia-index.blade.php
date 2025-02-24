<div>
    <x-mary-header title="Gerenciamento de Cadências" subtitle="Configure e gerencie suas sequências de mensagens" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Pesquisar cadência..." />
        </x-slot:middle>

        <x-slot:actions>
            <x-mary-button icon="o-plus" label="Nova Cadência" class="btn-primary" @click="$wire.showModal()" />
        </x-slot:actions>
    </x-mary-header>

    {{-- INFO: Aviso sobre como criar demandas --}}
    <x-mary-alert
        title="Dica: Como criar etapas para sua cadência"
        icon="o-light-bulb"
        description="{!! $descriptionCard !!}"
        class="bg-yellow-50 text-yellow-900 border-yellow-200 mb-4"
        dismissible
    />

    {{-- INFO: Aviso sobre como criar demandas --}}

    <x-mary-table
        :headers="$headers"
        :rows="$cadencias_table"
        class="bg-white"
        @row-click="$wire.edit($event.detail.id)"
        with-pagination
        per-page="perPage"
        :per-page-values="[3, 5, 10]"
    >

        {{-- Overrides `name` header --}}
        @scope('header_name', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `description` header --}}
        @scope('header_description', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `active` header --}}
        @scope('header_active', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Special `actions` slot --}}
        @scope('actions', $row)
            <div class="flex space-x-2">
                <x-mary-button icon="o-trash" spinner class="btn-sm btn-error" wire:click="delete({{ $row->id }})" title="Excluir" />
                <x-mary-button icon="o-plus" @click="window.location.href = '{{ route('etapas.index', ['cadenciaId' => $row->id]) }}'" spinner class="btn-sm btn-primary" title="adicionar etapas" />
            </div>
        @endscope

    </x-mary-table>

    {{-- INFO: modal slide --}}
    <x-mary-drawer
        wire:model="cadenciaModal"
        title="{{ $title }}"
        subtitle=""
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
        right
    >
        <x-mary-form wire:submit="save">
            <x-mary-input label="Nome do contato" wire:model="form.name" placeholder="Digite aqui o nome da cadência..." />

            {{-- INFO: range --}}
            <x-mary-alert
                title="Range"
                description="Insira o intervalo da cadência, dentro do horário comercial."
                icon="o-exclamation-triangle"
                dismissible
            />
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <x-mary-datetime label="Hora Inicio" wire:model="form.hora_inicio" icon="o-clock" type="time" />
                </div>
                <div class="space-y-2">
                    <x-mary-datetime label="Hora Fim" wire:model="form.hora_fim" icon="o-clock" type="time" />
                </div>
            </div>

            <x-mary-textarea
                label="Descrição"
                wire:model="form.description"
                placeholder="Your story ..."
                hint="Max 1000 chars"
                rows="5"
                inline />

            <x-mary-toggle label="Ativo" wire:model="form.active" />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.cadenciaModal = false" />
                <x-mary-button label="Save" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>
</div>
