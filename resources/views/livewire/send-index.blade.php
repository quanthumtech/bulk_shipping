<div>
    <x-mary-header title="Enviar mensagens" subtitle="Envio em massa" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Search..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button label="Grupos" icon="o-arrow-uturn-left" @click="window.location.href = '{{ route('group-send.index') }}'" />
            <x-mary-button icon="o-plus" class="btn-primary" @click="$wire.showModal()" />
        </x-slot:actions>
    </x-mary-header>

    {{-- INFO: Aviso sobre como criar demandas --}}
    <x-mary-alert
        title="Dica: Envie mensagens em massa e crie cadências personalizadas."
        icon="o-light-bulb"
        description="{!! $descriptionCard !!}"
        class="bg-yellow-50 text-yellow-900 border-yellow-200 mb-4"
        dismissible
    />

    {{-- INFO: table --}}
    <x-mary-table
        :headers="$headers"
        :rows="$group_table"
        class="bg-white"
        with-pagination per-page="perPage"
        :per-page-values="[3, 5, 10]"
    >

        {{-- Overrides `phone` header --}}
        @scope('header_formatted_phone_number', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `criado_por` header --}}
        @scope('header_criado_por', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `messagem` header --}}
        @scope('header_menssage', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `formatted_created_at` header --}}
        @scope('header_formatted_created_at', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Special `actions` slot --}}
        @scope('actions', $group_table)
            <x-mary-button icon="o-trash" wire:click="delete({{ $group_table->id }})" spinner class="btn-sm btn-error" />
        @endscope
    </x-mary-table>

    {{-- INFO: modal slide --}}
    <x-mary-drawer
        wire:model="sendModal"
        title="{{ $title }}"
        subtitle=""
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
        right
    >
        <x-mary-form wire:submit="save">
            <x-mary-choices label="Contatos" wire:model="form.phone_number" :options="$filteredContacts" allow-all />

            <x-mary-markdown wire:model="form.menssage_content" label="Mensagem" />

            <hr>

            <x-mary-file wire:model="form.file" multiple />

            <h3><strong>Configurar cadência</strong></h3>

            <hr>

            {{--<div class="grid grid-cols-2 gap-4">
                <x-mary-datepicker
                    label="Prazo inicial"
                    wire:model="form.date_inicial"
                    icon="o-calendar"
                    :config="$configDatePicker"
                    hint="Prazo definido pelo responsável com base na análise realizada."
                />
                <x-mary-datepicker
                    label="Prazo final"
                    wire:model="form.date_final"
                    icon="o-calendar"
                    :config="$configDatePicker"
                    hint="Prazo definido pelo responsável com base na análise realizada."
                />
            </div>--}}

            <x-mary-toggle label="Ativo" wire:model="form.active" />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.sendModal = false" />
                <x-mary-button label="Save" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>

</div>
