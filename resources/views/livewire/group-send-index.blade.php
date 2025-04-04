<div>
    <x-mary-header title="Grupos de trabalhos" subtitle="Gerenciar grupos" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Search..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button icon="o-plus" class="btn-primary" @click="$wire.showModal()" />
        </x-slot:actions>
    </x-mary-header>

    {{-- INFO: Aviso sobre como criar demandas --}}
    <x-mary-alert
        title="Dica: Como criar suas demandas"
        icon="o-light-bulb"
        description="{!! $descriptionCard !!}"
        class="bg-yellow-50 text-yellow-900 border-yellow-200 mb-4"
        dismissible
    />

    {{-- INFO: modal slide --}}
    <x-mary-drawer
        wire:model="groupModal"
        title="{{ $title }}"
        subtitle=""
        separator
        with-close-button
        close-on-escape
        class="w-11/13 lg:w-1/2"
        right
    >
        <x-mary-form wire:submit="save">

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <x-mary-input label="Titulo" wire:model="form.title" />
                </div>
                <div class="space-y-2">
                    <x-mary-input label="Sub Titulo" wire:model="form.sub_title" />
                </div>
            </div>

            {{--
                <x-mary-choices label="Contatos" wire:model="form.phone_number" :options="$contatos" allow-all />


                <x-mary-choices
                    label="Contatos"
                    wire:model="form.phone_number"
                    :options="$contatos"
                    placeholder="Clique no 'X' antes de buscar..."
                    debounce="300ms"
                    min-chars="2"
                    searchable
                    no-result-text="Nenhum contato encontrado."
                    search-function="searchContatosf"
                />
            --}}

            <x-mary-textarea
                label="Descrição"
                wire:model="form.description"
                placeholder="Your story ..."
                hint="Max 1000 chars"
                rows="5"
                inline />

            <hr>

            <x-mary-file
                change-text="Note Image"
                crop-text="Crop"
                crop-title-text="Note Image"
                crop-cancel-text="Cancel"
                crop-save-text="Crop"
                wire:model="form.image"
                accept="image/png"
                crop-after-change
            >
                <img
                    src="{{
                        $form->image ? Storage::url($form->image) : 'https://via.placeholder.com/500x200'
                    }}"
                    alt="Note Image"
                    class="h-40 rounded-lg"
                />

            </x-mary-file>

            <x-mary-toggle label="Ativo" wire:model="form.active" />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.groupModal = false" />
                <x-mary-button label="Save" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>

    {{-- INFO: cards grupos --}}
    <div class="grid lg:grid-cols-3 gap-5">
        @foreach ($groups as $group)
            <x-mary-card
                title="{{ $group->title }}"
                class="bg-gray-50 shadow-lg"
                subtitle="{{ $group->sub_title }}"
                separator
                progress-indicator
                >
                <x-slot:figure>
                    <img src="{{ Storage::url($group->image) }}" class="h-28 w-full object-cover" />
                </x-slot:figure>
                <x-slot:menu>
                    <x-mary-badge value="#{{ $group->id }}" class="badge-primary" />
                </x-slot:menu>
                <x-mary-button label="Mensagens" @click="window.location.href = '{{ route('send.index', ['groupId' => $group->id]) }}'" />
                <x-mary-button icon="o-pencil-square" @click="$wire.edit({{ $group->id }})" class="btn-primary" />
                <x-mary-button icon="o-trash" wire:click="delete({{ $group->id }})" class="btn-error end" />
            </x-mary-card>
        @endforeach
    </div>

</div>
