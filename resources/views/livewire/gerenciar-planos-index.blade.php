<div>
    <x-mary-header title="Gerenciar Planos" subtitle="Crie e gerencie planos de assinatura" separator>
        <x-slot:actions>
            <x-mary-input icon="o-magnifying-glass" wire:model.live="search" placeholder="Buscar planos..." />
            <x-mary-button label="Novo Plano" icon="o-plus" class="btn-primary" wire:click="showModal" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-table 
        :headers="$headers" 
        :rows="$plans" 
        striped with-pagination 
        class="bg-base-100"
        per-page="perPage" 
        :per-page-values="[5, 10, 25]"
    >

        {{-- Overrides `name` header --}}
        @scope('header_name', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `price` header --}}
        @scope('header_price', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `max_cadence_flows` header --}}
        @scope('header_max_cadence_flows', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope
        
        {{-- Overrides `max_attendance_channels` header --}}
        @scope('header_max_attendance_channels', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `max_daily_leads` header --}}
        @scope('header_max_daily_leads', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `message_storage_days` header --}}
        @scope('header_message_storage_days', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `active` header --}}
        @scope('header_active', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `actions` header --}}
        @scope('header_actions', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overriders `support_level` header --}}
        @scope('header_support_level', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `support_level` cell --}}
        @scope('cell_support_level', $row)
            <span class="badge badge-primary">{{ $row->support_name }}</span>
        @endscope

        @scope('cell_active', $row)
            <span class="badge {{ $row->active ? 'badge-success' : 'badge-error' }}">{{ $row->active_name }}</span>
        @endscope

        @scope('actions', $row)
            <div class="flex gap-1">
                <x-mary-button 
                    icon="o-pencil-square"
                    wire:click="showModal({{ $row->id }})"
                    class="btn-sm btn-primary"
                />
                <x-mary-button
                    icon="o-trash"
                    wire:click="confirmDelete({{ $row->id }})" 
                    spinner
                    class="btn-sm btn-error"
                />
            </div>
        @endscope
    </x-mary-table>

    {{-- Modal para Criar/Editar Plano --}}
    <x-mary-drawer
        wire:model="modal"
        title="{{ $title }}"
        subtitle=""
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
        right
    >
        <x-mary-form wire:submit="save">
            <div class="space-y-4">
                <x-mary-input label="Nome do Plano" wire:model="form.name" required />
                <x-mary-input label="Preço (R$)" type="number" step="0.01" wire:model="form.price" />
                <x-mary-select label="Ciclo de Cobrança" :options="$billingOptions" wire:model="form.billing_cycle" />
                
                <div class="grid grid-cols-2 gap-4">
                    <x-mary-input label="Máx. Fluxos de Cadência" type="number" wire:model="form.max_cadence_flows" />
                    <x-mary-input label="Máx. Canais de Atendimento" type="number" wire:model="form.max_attendance_channels" />
                    <x-mary-input label="Máx. Leads/Dia" type="number" wire:model="form.max_daily_leads" />
                    <x-mary-input label="Dias de Armazenamento" type="number" wire:model="form.message_storage_days" />
                </div>

                <x-mary-select label="Nível de Suporte" 
                    :options="[
                        ['id' => 'basic', 'name' => 'Básico'],
                        ['id' => 'priority', 'name' => 'Prioritário'],
                        ['id' => 'dedicated', 'name' => 'Dedicado']
                    ]" 
                    wire:model="form.support_level" />

                <x-mary-textarea label="Descrição" wire:model="form.description" rows="3" />

                <div class="grid grid-cols-3 gap-4">
                    <x-mary-toggle wire:model="form.has_crm_integration">
                        <x-slot:label>Integração CRM</x-slot:label>
                    </x-mary-toggle>
                    <x-mary-toggle wire:model="form.has_chatwoot_connection">
                        <x-slot:label>Chatwoot</x-slot:label>
                    </x-mary-toggle>
                    <x-mary-toggle wire:model="form.has_scheduled_sending">
                        <x-slot:label>Envio Programado</x-slot:label>
                    </x-mary-toggle>
                    <x-mary-toggle wire:model="form.has_operational_reports">
                        <x-slot:label>Relatórios Operacionais</x-slot:label>
                    </x-mary-toggle>
                    <x-mary-toggle wire:model="form.has_performance_panel">
                        <x-slot:label>Painel de Performance</x-slot:label>
                    </x-mary-toggle>
                    <x-mary-toggle wire:model="form.is_custom">
                        <x-slot:label>Customizado</x-slot:label>
                    </x-mary-toggle>
                </div>

                <x-mary-toggle wire:model="form.active">
                    <x-slot:label>Plano Ativo</x-slot:label>
                </x-mary-toggle>
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancelar" wire:click="$set('modal', false)" />
                <x-mary-button type="submit" label="{{ $editMode ? 'Atualizar' : 'Criar' }}" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>

    {{-- Modal de Confirmação de Exclusão --}}
    <x-mary-modal wire:model="confirmingDelete" title="Confirmar Exclusão">
        <p class="text-base-content">
            Tem certeza que deseja excluir este plano? Esta ação não pode ser desfeita.
        </p>

        <x-slot:actions>
            <x-mary-button label="Cancelar" wire:click="cancelDelete" />
            <x-mary-button 
                label="Excluir" 
                wire:click="delete"
                class="btn-error" 
                spinner
            />
        </x-slot:actions>
    </x-mary-modal>
</div>