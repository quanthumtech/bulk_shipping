<div>
    <x-mary-header title="Lista de contatos" subtitle="Gerencie sua lista de contatos" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Buscar contatos..." />
        </x-slot:middle>

        <x-slot:actions>
            <x-mary-button label="Grupos" icon="o-rectangle-group" @click="window.location.href = '{{ route('group-send.index') }}'" />
            <x-mary-button icon="o-plus" class="btn-primary" @click="$wire.showModal()" />
        </x-slot:actions>
    </x-mary-header>

    {{-- INFO: Aviso sobre como criar demandas --}}
    <x-mary-alert
        title="Sobre a lista de contatos"
        icon="o-light-bulb"
        description="{!! $descriptionCard !!}"
        class="bg-yellow-50 text-yellow-900 border-yellow-200 mb-4"
        dismissible
    />

    {{-- INFO: table --}}
    <x-mary-table
        :headers="$headers"
        :rows="$contatos_table"
        class="bg-white"
        with-pagination
        per-page="perPage"
        :per-page-values="[3, 5, 10]"
    >

        {{-- Overrides `phone` header --}}
        @scope('header_phone', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `name` header --}}
        @scope('header_name', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

    </x-mary-table>

    {{-- INFO: Pagination

    <div class="flex justify-between items-center mt-4">
        <x-mary-button label="Anterior" icon="o-arrow-left" wire:click="previousPage" :disabled="$page === 1" />
        <span>Página {{ $page }} </span>
        <x-mary-button label="Próxima" icon="o-arrow-right" wire:click="nextPage"  />
    </div>

    --}}

    {{-- INFO: modal slide --}}

    {{-- INFO: modal slide --}}
    <x-mary-drawer
        wire:model="contactModal"
        title="{{ $title }}"
        subtitle=""
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
        right
    >
        <x-mary-form wire:submit="save">
            <x-mary-input label="Nome do contato" wire:model="form.contact_name" />
            <x-mary-input label="Telefone" wire:model="form.phone_number" x-mask="(99) 99999-9999" hint="Insira apenas número de whatsapp" placeholder="(00) 00000-0000" />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.contactModal = false" />
                <x-mary-button label="Save" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>
</div>
