<div>
    <x-mary-header title="Cards Leads" subtitle="SyncFlow permite gerenciar leads capturados, criar e administrar cadências de forma eficiente." separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Buscar lead..." />
        </x-slot:middle>
    </x-mary-header>

    {{-- Modal para edição/criação de leads --}}
    <x-mary-modal wire:model="syncLeadsModal" class="backdrop-blur">
        <x-mary-form wire:submit="save">
            <x-mary-input label="Name" wire:model="form.contact_name" />
            <x-mary-input label="Number" wire:model="form.contact_number" />
            <x-mary-input label="Email" wire:model="form.contact_email" />
            <x-mary-input label="Contato empresa" wire:model="form.contact_number_empresa" />
            <x-mary-input label="Stage" wire:model="form.estagio" />
            <x-mary-select label="Cadência" wire:model="form.cadenciaId" :options="$cadencias" />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.syncLeadsModal = false" />
                <x-mary-button label="Create" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Modal para atribuir cadência --}}
    <x-mary-modal wire:model="cadenceModal" class="backdrop-blur">
        <x-mary-form wire:submit="cadenceSave">
            <div class="mb-5 text-base-content">{{ $this->title }}</div>
            <x-mary-select label="Cadência" wire:model="form.cadenciaId" :options="$cadencias" />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.cadenceModal = false" />
                <x-mary-button label="Create" class="btn-primary" type="submit" spinner="cadenceSave" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Modal para histórico de conversas --}}
    <x-mary-modal wire:model="historyModal" class="backdrop-blur">
        <div class="mb-5 text-base-content">
            <h2 class="text-lg font-bold">Histórico de Conversas - {{ $selectedLead->contact_name ?? 'Lead' }}</h2>
        </div>
        <div class="max-h-96 overflow-y-auto">
            @if (empty($conversationHistory))
                <p class="text-gray-500">Nenhuma mensagem encontrada.</p>
            @else
                <ul class="space-y-4">
                    @foreach ($conversationHistory as $message)
                        <li class="border-b pb-2">
                            <p class="text-sm text-gray-600">{{ $message['created_at'] }}</p>
                            <p class="text-base">{{ $message['content'] }}</p>
                            <p class="text-xs text-gray-400">Mensagem ID: {{ $message['message_id'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <x-slot:actions>
            <x-mary-button label="Fechar" @click="$wire.historyModal = false" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Cards Leads --}}
    <div class="grid lg:grid-cols-3 gap-5 mt-4">
        @foreach ($syncFlowLeads as $sync)
            <x-mary-card
                title="{{ $sync->contact_name ?? 'Não definido' }}"
                class="bg-base-100 shadow-lg"
                subtitle="Telefone: {{ $sync->contact_number }} | Email: {{ $sync->contact_email }} | Cadência: {{ $sync->cadencia->name ?? 'Não definido' }}"
                separator
            >
                <x-slot:menu>
                    <x-mary-badge value="#{{ $sync->estagio ?? 'Não definido' }}" class="badge-primary" />
                </x-slot:menu>
                <x-mary-button label="Atribuir cadência" @click="$wire.cadence({{ $sync->id }})" />
                <x-mary-dropdown>
                    <x-mary-menu-item title="Ver Histórico" icon="o-envelope" @click="$wire.viewHistory({{ $sync->id }})" />
                    <x-mary-menu-item title="Editar" icon="o-pencil-square" @click="$wire.edit({{ $sync->id }})" />
                    <x-mary-menu-item title="Excluir" icon="o-trash" wire:click="delete({{ $sync->id }})" />
                </x-mary-dropdown>
            </x-mary-card>
        @endforeach
    </div>

    {{-- Paginação --}}
    <div class="mt-4">
        {{ $syncFlowLeads->links() }}
    </div>
</div>
