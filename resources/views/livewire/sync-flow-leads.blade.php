<div>
    <x-mary-header title="Cards Leads" subtitle="SyncFlow permite gerenciar leads capturados, criar e administrar cadências de forma eficiente." separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Buscar lead..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button label="Filtros Avançados" icon="o-funnel" @click="$wire.openFilterDrawer()" />
            <x-mary-button label="Criar Lead" icon="o-plus" class="btn-primary ml-2" @click="$wire.showModal()" />
        </x-slot:actions>
    </x-mary-header>

    <!-- Filter Drawer -->
    <x-mary-drawer
        wire:model="showFilterDrawer"
        title="Filtros de Leads"
        subtitle="Ajuste os filtros para personalizar a visualização dos leads. Use 'Data Inicial/Final' para filtrar por intervalo de criação."
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
        right>
        <div class="space-y-4">
            <x-mary-input
                label="Nome do Contato"
                wire:model.live="contactNameFilter"
                placeholder="Digite o nome do contato" />
            <x-mary-input
                label="Número de Contato"
                wire:model.live="contactNumberFilter"
                placeholder="Digite o número de contato" />
            <x-mary-input
                label="Email"
                wire:model.live="contactEmailFilter"
                placeholder="Digite o email do contato" />
            <x-mary-select
                label="Estágio"
                :options="$estagioOptions"
                option-label="name"
                option-value="id"
                wire:model.live="estagioFilter"
                placeholder="Selecione o estágio" />
            <x-mary-select
                label="Situação de Contato"
                :options="$situacaoContatoOptions"
                option-label="name"
                option-value="id"
                wire:model.live="situacaoContatoFilter"
                placeholder="Selecione a situação" />
            <x-mary-select
                label="Cadência"
                :options="$cadenciaOptions"
                option-label="name"
                option-value="id"
                wire:model.live="cadenciaFilter"
                placeholder="Selecione a cadência" />
            <x-mary-select
                label="Origem"
                :options="[
                    ['id' => '', 'name' => 'Todas'],
                    ['id' => '1', 'name' => 'Webhook'],
                    ['id' => '0', 'name' => 'Manual'],
                ]"
                option-label="name"
                option-value="id"
                wire:model.live="isFromWebhookFilter"
                placeholder="Selecione a origem" />
            <x-mary-input
                label="Data Inicial"
                type="date"
                wire:model.live="startDate"
                placeholder="Selecione a data inicial" />
            <x-mary-input
                label="Data Final"
                type="date"
                wire:model.live="endDate"
                placeholder="Selecione a data final" />
        </div>

        <x-slot:actions>
            <x-mary-button
                label="Resetar"
                icon="o-x-mark"
                class="btn-ghost"
                wire:click="resetFilters" />
            <x-mary-button
                label="Aplicar"
                icon="o-check"
                class="btn-primary"
                wire:click="applyFilters" />
        </x-slot:actions>
    </x-mary-drawer>

    <!-- Modal para edição/criação de leads -->
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

    <!-- Modal para atribuir cadência -->
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

    <!-- Cards Leads -->
    <div class="grid lg:grid-cols-3 gap-5 mt-4">
        @foreach ($syncFlowLeads as $sync)
            @if ($sync)
                <x-mary-card
                    title="{{ Str::limit($sync->contact_name ?? 'Não definido', 30) }}"
                    class="shadow-lg {{ empty($sync->contact_email) || empty($sync->contact_number) ? 'bg-orange-100 border-2 border-orange-500' : 'bg-base-100' }}"
                    subtitle="Criado: {{ $sync->created_at->format('d/m/Y H:i:s') }} | Telefone: {{ $sync->contact_number ?? 'Não informado' }} | Email: {{ $sync->contact_email ?? 'Não informado' }} | Cadência: {{ $sync->cadencia?->name ?? 'Não definido' }} | ID: {{ $sync->id ?? 'N/A' }} | ID Card: {{ $sync->id_card ?? 'N/A' }} | Contact ID: {{ $sync->contact_id ?? 'N/A' }}"
                    separator
                >
                    <x-mary-collapse :open="$show[$sync->id] ?? false" separator class="mb-4">
                        <x-slot:heading>
                            <div wire:click="toggleCollapse({{ $sync->id }})" class="cursor-pointer text-base-content">
                                Mais Detalhes
                            </div>
                        </x-slot:heading>
                        <x-slot:content>
                            <div class="text-sm text-gray-600 grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="flex items-center">
                                    <span class="font-semibold mr-1">Telefone:</span>
                                    <span>{{ $sync->contact_number ?? 'Não informado' }}</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="font-semibold mr-1">Email:</span>
                                    <span>{{ $sync->contact_email ?? 'Não informado' }}</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="font-semibold mr-1">Cadência:</span>
                                    <span>{{ $sync->cadencia?->name ?? 'Não definido' }}</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="font-semibold mr-1">Situação Contato:</span>
                                    <span>{{ $sync->situacao_contato ?? 'Não informado' }}</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="font-semibold mr-1">Nome Vendedor:</span>
                                    <span>{{ $sync->nome_vendedor ?? 'Não informado' }}</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="font-semibold mr-1">Origem:</span>
                                    <span>
                                        @if(!empty($sync->id_card) && $sync->id_card !== 'Não fornecido')
                                            <x-mary-badge value="Webhook" class="badge-success" />
                                        @else
                                            <x-mary-badge value="Manual" class="badge-info" />
                                        @endif
                                    </span>
                                </div>
                                <div class="flex items-center md:col-span-2">
                                    <span class="font-semibold mr-1">Status Chatwoot:</span>
                                    @if(!empty($sync->chatwoot_status))
                                        <x-mary-badge value="{{ $sync->chatwoot_status }}" class="badge-primary" />
                                    @else
                                        <x-mary-badge value="Não informado" class="badge-secondary" />
                                    @endif
                                </div>
                            </div>
                        </x-slot:content>
                    </x-mary-collapse>
                    <x-slot:menu>
                        <x-mary-badge value="#{{ $sync->estagio ?? 'Não definido' }}" class="badge badge-primary" />
                        @if (empty($sync->contact_email) || empty($sync->contact_number) || $sync->contact_email === 'Não fornecido' || $sync->contact_number === 'Não fornecido')
                            <x-mary-badge value="#Faltam Dados" class="badge badge-warning font-bold" style="color: black !important;" />
                        @endif
                    </x-slot:menu>
                    <div class="flex items-center gap-2">
                        <x-mary-button label="Atribuir cadência" @click="$wire.cadence({{ $sync->id }})" class="btn-sm" />
                        <x-mary-dropdown>
                            <x-mary-menu-item title="Editar" icon="o-pencil-square" @click="$wire.edit({{ $sync->id }})" />
                            <x-mary-menu-item title="Excluir" icon="o-trash" wire:click="delete({{ $sync->id }})" />
                            <x-mary-menu-item title="Ver Detalhes" icon="o-information-circle" link="{{ route('lead.details', ['leadId' => $sync->id]) }}" />
                        </x-mary-dropdown>
                    </div>
                </x-mary-card>
            @endif
        @endforeach
    </div>

    <!-- Paginação -->
    <div class="mt-4">
        {{ $syncFlowLeads->links() }}
    </div>
</div>