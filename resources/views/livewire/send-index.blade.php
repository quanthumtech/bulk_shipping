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

        {{-- Overrides `contact_name` header --}}
        @scope('header_contact_name', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

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
        class="w-11/12 lg:w-1/2"
        right
    >
        <x-mary-form wire:submit="save">
            {{-- <x-mary-choices label="Contatos" wire:model="form.phone_number" :options="$filteredContacts" allow-all /> --}}

            <x-mary-choices
                label="Contatos"
                wire:model="form.phone_number"
                :options="$contatos"
                placeholder="Clique no 'X' antes de buscar..."
                debounce="300ms" {{-- Default is `250ms`--}}
                min-chars="2" {{-- Default is `0`--}}
                searchable
                no-result-text="Nenhum contato encontrado."
                search-function="searchContatosf"
            />

            <x-mary-alert
                title="Como enviar imagens? 📸"
                description="Siga estes passos:
                    1. Clique no ícone de imagem para selecionar sua foto.
                    2. Envie a imagem junto com a mensagem, como no WhatsApp!
                    3. Toque no ícone de olho para ver como ficou.
                    4. Se a imagem estiver errada, apague e escolha outra."
                icon="o-exclamation-triangle"
            />

            <x-mary-markdown wire:model="form.menssage_content" label="Mensagem">
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

            <hr>

           {{--
            <x-mary-popover position="top-start" offset="20">
                <x-slot:trigger>
                    <x-mary-button label="Configurar cadência (Opcional)" />
                </x-slot:trigger>
                <x-slot:content>
                    Logo vamos aprimorar a cadência,
                    por enquanto o envio de intervalo
                    será apenas um.
                </x-slot:content>
            </x-marypopover>

            <hr>

            <x-mary-datepicker
                label="Data inicial"
                wire:model="form.start_date"
                icon="o-calendar"
                :config="$configDatePicker"
                hint="Data de início do envio."
            />

            <x-mary-input
                label="Intervalo (em dias)"
                type="number"
                wire:model="form.interval"
                hint="Intervalo entre as mensagens (em dias)."
                min="1"
            />

            <x-mary-textarea
                label="Mensagem"
                wire:model="form.message_interval"
                placeholder="Digite aqui ..."
                hint="Max 1000 chars"
                rows="5"
                inline
            />
            --}}

            <x-mary-select label="Cadêcnia (Opcional)" wire:model="form.cadencias" :options="$cadencias" />

            <x-mary-toggle label="Ativo" wire:model="form.active" />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.sendModal = false" />
                <x-mary-button label="Save" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>

</div>
