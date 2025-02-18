<div>
    <x-mary-header title="Gerenciamento de Etapas" subtitle="Adicione e organize as etapas da sua cadência">
        <x-slot:actions>
            <x-mary-button label="Cadências" icon="o-arrow-uturn-left" @click="window.location.href = '{{ route('cadencias.index') }}'" />
            <x-mary-button icon="o-plus" label="Nova Etapa" class="btn-primary" @click="$wire.showModal()" />
        </x-slot:actions>
    </x-mary-header>

    @if (session()->has('success'))
        <x-mary-alert title="Sucesso!" description="{{ session('success') }}" class="bg-green-100 text-green-900 mb-4" dismissible />
    @endif

    <x-mary-table :rows="$etapas" :headers="$headers" class="bg-white" with-pagination>
        @scope('titulo', $header)
            <h3 class="text-xl font-bold">{{ $header['label'] }}</h3>
        @endscope

        @scope('tempo', $header)
            <h3 class="text-xl font-bold">{{ $header['label'] }}</h3>
        @endscope

        @scope('unidade_tempo', $header)
            <h3 class="text-xl font-bold">{{ $header['label'] }}</h3>
        @endscope

        @scope('actions', $etapa)
            <x-mary-button icon="o-trash" class="btn-sm btn-danger" wire:click="delete({{ $etapa->id }})" title="Excluir" />
        @endscope
    </x-mary-table>

    <x-mary-drawer wire:model="etapaModal" title="Nova Etapa" class="w-11/12 lg:w-1/3" right>
        <x-mary-form wire:submit="save">
            <x-mary-input label="Título" wire:model="titulo" />
            <x-mary-input label="Tempo" type="number" wire:model="tempo" min="1" max="30" />
            <x-mary-select label="Unidade de Tempo" wire:model="unidade_tempo" :options="$options" />

            <x-slot:actions>
                <x-mary-button label="Cancelar" @click="$wire.etapaModal = false" />
                <x-mary-button label="Salvar" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>
</div>
