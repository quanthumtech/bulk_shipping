<div>
    <x-mary-header title="Gerenciamento de Etapas" subtitle="Adicione e organize as etapas da sua cadência">
        <x-slot:actions>
            <x-mary-button label="Cadências" icon="o-arrow-uturn-left" @click="window.location.href = '{{ route('cadencias.index') }}'" />
            <x-mary-button icon="o-plus" label="Nova Etapa" class="btn-primary" @click="$wire.showModal()" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-table
        :rows="$etapas"
        :headers="$headers"
        class="bg-white"
        striped @row-click="$wire.edit($event.detail.id)"
        with-pagination
        per-page="perPage"
        :per-page-values="[3, 5, 10]"
        >
        @scope('header_titulo', $header)
            <h3 class="text-xl font-bold text-black">{{ $header['label'] }}</h3>
        @endscope

        @scope('header_tempo', $header)
            <h3 class="text-xl font-bold text-black">{{ $header['label'] }}</h3>
        @endscope

        @scope('header_unidade_tempo', $header)
            <h3 class="text-xl font-bold text-black">{{ $header['label'] }}</h3>
        @endscope

        @scope('actions', $etapa)
            <x-mary-button icon="o-trash" spinner class="btn-sm btn-error" wire:click="delete({{ $etapa->id }})" title="Excluir" />
        @endscope
    </x-mary-table>

    <x-mary-drawer
        wire:model="etapaModal"
        title="{{ $title }}"
        subtitle=""
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
        right
        >

        <x-mary-form wire:submit="save">
            <x-mary-input label="Título" wire:model="form.titulo" />
            <x-mary-input label="Tempo" type="number" wire:model="form.tempo" min="1" max="30" />
            <x-mary-select label="Unidade de Tempo" wire:model="form.unidade_tempo" :options="$options" />

            <x-mary-select label="Tipo de envio" wire:model="form.type_send" :options="$optionsSend" />
            <x-mary-markdown wire:model="form.message_content" label="Mensagem">
                <x-slot:append>
                    <x-mary-button
                        icon="o-sparkles"
                        wire:click="generateSuggestion"
                        spinner
                        class="btn-ghost"
                        tooltip="Sugerir mensagem com AI"
                    />
                </x-slot:append>
            </x-mary-markdown>

            <x-slot:actions>
                <x-mary-button label="Cancelar" @click="$wire.etapaModal = false" />
                <x-mary-button label="Salvar" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>
</div>
