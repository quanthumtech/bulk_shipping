<div>
    <x-mary-header title="Cards Leads" subtitle="SyncFlow permite gerenciar leads capturados, criar e administrar cadências de forma eficiente." separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Buscar lead..." />
        </x-slot:middle>
    </x-mary-header>

    {{-- INFO: Modal users --}}
    <x-mary-modal wire:model="syncLeadsModal" class="backdrop-blur">
        <x-mary-form wire:submit="save">

            {{-- INFO: campos --}}
            <x-mary-input label="Name" wire:model="form.contact_name" />
            <x-mary-input label="Number" wire:model="form.contact_number" />
            <x-mary-input label="Email" wire:model="form.contact_email" />
            <x-mary-input label="Contato empresa" wire:model="form.contact_number_empresa" />
            <x-mary-input label="Stage" wire:model="form.estagio" />
            <x-mary-select label="Cadêcnia" wire:model="form.cadenciaId" :options="$cadencias" />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.syncLeadsModal = false" />
                <x-mary-button label="Create" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- INFO: Modal designar cadencia --}}
    <x-mary-modal wire:model="cadenceModal" class="backdrop-blur">
        <x-mary-form wire:submit="cadenceSave">
            <div class="mb-5">{{ $this->title }}</div>

            {{-- INFO: campos --}}
            <x-mary-select label="Cadêcnia" wire:model="form.cadenciaId" :options="$cadencias" />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.cadenceModal = false" />
                <x-mary-button label="Create" class="btn-primary" type="submit" spinner="cadenceSave" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- INFO: Cards Leads --}}
    <div class="grid lg:grid-cols-3 gap-5 mt-4">
        @foreach ($syncFlowLeads as $sync)
            <x-mary-card
                title="{{ $sync->contact_name ?? 'Não definido' }}"
                class="bg-gray-50 shadow-lg"
                subtitle="Telefone: {{ $sync->contact_number }} | Email: {{ $sync->contact_email }} | Cadência: {{ $sync->cadencia->name ?? 'Não definido' }}"
                separator
            >
                <x-slot:menu>
                    <x-mary-badge value="#{{ $sync->estagio ?? 'Não definido' }}" class="badge-primary" />
                </x-slot:menu>
                <x-mary-button label="Atribuir cadência" @click="$wire.cadence({{ $sync->id }})" />
                <x-mary-button icon="o-pencil-square" @click="$wire.edit({{ $sync->id }})" class="btn-primary" />
                <x-mary-button icon="o-trash" wire:click="delete({{ $sync->id }})" class="btn-error end" />
            </x-mary-card>
        @endforeach
    </div>

    {{-- PAGINAÇÃO --}}
    <div class="mt-4">
        {{ $syncFlowLeads->links() }}
    </div>
</div>
