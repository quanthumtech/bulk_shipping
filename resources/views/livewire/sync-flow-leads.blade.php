<div>
    <x-mary-header title="Cards Leads" subtitle="SyncFlow permite gerenciar leads capturados, criar e administrar cadências de forma eficiente." separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Buscar lead..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button label="Criar Lead" icon="o-plus" class="btn-primary ml-2"  @click="$wire.showModal()" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Modal para edição/criação de leads --}}
    <x-mary-modal wire:model="syncLeadsModal" title="{{ $title }}" class="backdrop-blur">
        <x-mary-form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="Name" wire:model="form.contact_name" :nullable="true" />
                <x-mary-input label="Number" wire:model="form.contact_number" :nullable="true" />
                <x-mary-input label="Email" wire:model="form.contact_email" :nullable="true" />
                <x-mary-input label="Contato empresa" wire:model="form.contact_number_empresa" :nullable="true" />
            </div>
            <x-mary-input label="Stage" wire:model="form.estagio" :nullable="true" />
            <x-mary-input
                label="Situação de contato"
                wire:model="form.situacao_contato"
                hint="Este campo é atualizado automaticamente conforme o envio de mensagens. O valor padrão é 'Tentativa de Contato'."
                :nullable="true"
                disabled
            />
            <x-mary-select label="Cadência" wire:model="form.cadenciaId" :options="$cadencias" :nullable="true" />

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
                title="{{ Str::limit($sync->contact_name ?? 'Não definido', 40) }}"
                class="shadow-lg {{ empty($sync->contact_email) || empty($sync->contact_number) ? 'bg-orange-100 border-2 border-orange-500' : 'bg-base-100' }}"
                subtitle="Telefone: {{ $sync->contact_number ?? 'Não informado' }} | Email: {{ $sync->contact_email ?? 'Não informado' }} | Cadência: {{ $sync->cadencia?->name ?? 'Não definido' }}"
                separator
            >
                <x-slot:menu>
                    <x-mary-badge value="#{{ $sync->estagio ?? 'Não definido' }}" class="badge badge-primary" />
                    @if (empty($sync->contact_email) || empty($sync->contact_number) || $sync->contact_email === 'Não fornecido' || $sync->contact_number === 'Não fornecido')
                        <x-mary-badge value="#Faltam Dados" class="badge badge-warning font-bold" style="color: black !important;" />
                    @endif
                </x-slot:menu>
                <x-mary-button label="Atribuir cadência" @click="$wire.cadence({{ $sync->id }})" />
                <x-mary-dropdown>
                    <x-mary-menu-item title="Editar" icon="o-pencil-square" @click="$wire.edit({{ $sync->id }})" />
                    <x-mary-menu-item title="Excluir" icon="o-trash" wire:click="delete({{ $sync->id }})" />
                    <x-mary-menu-item title="Ver Histórico" icon="o-envelope" link="{{ route('lead.conversation.history', ['leadId' => $sync->id]) }}" />
                </x-mary-dropdown>
            </x-mary-card>
        @endforeach
    </div>

    {{-- Paginação --}}
    <div class="mt-4">
        {{ $syncFlowLeads->links() }}
    </div>
</div>


