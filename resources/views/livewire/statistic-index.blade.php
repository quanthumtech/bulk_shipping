<div>
    <x-mary-header
        title="Dashboard"
        subtitle="Visão geral de estatísticas e métricas de desempenho"
        separator
    />

    @if($isAdmin)
    <div class="mb-6">
        <x-mary-collapse :open="$show['open_filtro']" separator>
            <x-slot:heading>
                <div wire:click="toggleCollapse('open_filtro')" class="cursor-pointer text-base-content">
                    Filtrar Avançado Dashboard
                    <x-mary-icon name="o-funnel" />
                </div>
            </x-slot:heading>
            <x-slot:content>
                <div class="grid grid-cols-2 gap-4 mt-4 mb-6">
                    <div class="space-y-2">
                        <label for="user-select" class="block text-sm font-medium mb-2">Filtrar por usuário:</label>
                        <select wire:model="selectedUserId" id="user-select" class="select select-bordered w-full">
                            <option value="">Todos os Usuários</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    
                        <label for="start-date" class="block text-sm font-medium mb-2">Data Início:</label>
                        <input type="date" wire:model="startDate" id="start-date" class="input input-bordered w-full">
                    </div>
        
                    <div class="space-y-2">
                        <label for="end-date" class="block text-sm font-medium mb-2">Data Fim:</label>
                        <input type="date" wire:model="endDate" id="end-date" class="input input-bordered w-full">
                
                        <label for="status-select" class="block text-sm font-medium mb-2">Filtrar por Status do Lead:</label>
                        <select wire:model="selectedStatus" id="status-select" class="select select-bordered w-full">
                            <option value="">Todos os Status</option>
                            @foreach($leadStatuses as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </x-slot:content>
        </x-mary-collapse>
    </div>
    @endif

    <div class="grid lg:grid-cols-4 gap-5">
        <x-mary-stat
            title="Total de Leads Ativos"
            value="{{ $leadsAtivos }}"
            icon="o-users"
            class="bg-base-100 shadow-lg"
        />

        <x-mary-stat
            title="Total de Grupos Ativos"
            value="{{ $gruposAtivos }}"
            icon="o-rectangle-group"
            class="bg-base-100 shadow-lg"
        />

        <x-mary-stat
            title="Total de Contatos"
            value="{{ $contatosTotais }}"
            icon="o-user-circle"
            class="bg-base-100 shadow-lg"
        />

        <x-mary-stat
            title="Total de Cadências Ativas"
            value="{{ $cadenciasAtivas }}"
            icon="o-clock"
            class="bg-base-100 shadow-lg"
        />
    </div>

    <div class="grid lg:grid-cols-2 gap-10 mt-10">
        <div class="bg-base-100 p-4 rounded-lg shadow-lg w-full">
            <h3 class="text-lg font-semibold mb-4 text-center text-base-content">
                Leads (Atribuídos, Sem Cadência, Em Progresso, Finalizados)
            </h3>
            <x-mary-chart wire:model="leadsChart" class="w-full h-72" />
        </div>

        <div class="bg-base-100 p-4 rounded-lg shadow-lg w-full">
            <h3 class="text-lg font-semibold mb-4 text-center text-base-content">
                Frequência de Envios (por dia)
            </h3>
            <x-mary-chart wire:model="frequenciaChart" class="w-full h-72" />
        </div>

        <div class="bg-base-100 p-4 rounded-lg shadow-lg w-full">
            <h3 class="text-lg font-semibold mb-4 text-center text-base-content">
                Taxa de Falha de Envio (por canal)
            </h3>
            <div class="grid grid-cols-2 gap-6 justify-items-center">
                <div class="text-center">
                    <x-mary-progress-radial :value="$emailRate" unit="%" class="text-warning" style="--size: 8rem; --thickness: 8px;" />
                    <p class="mt-2 font-medium">Email<br>{{ $emailRate }}%</p>
                </div>
                <div class="text-center">
                    <x-mary-progress-radial :value="$whatsappRate" unit="%" class="text-primary" style="--size: 8rem; --thickness: 8px;" />
                    <p class="mt-2 font-medium">WhatsApp<br>{{ $whatsappRate }}%</p>
                </div>
            </div>
        </div>

        <div class="bg-base-100 p-4 rounded-lg shadow-lg w-full">
            <h3 class="text-lg font-semibold mb-4 text-center text-base-content">
                Taxa de Sucesso de Envio (por canal)
            </h3>
            <div class="grid grid-cols-2 gap-6 justify-items-center">
                <div class="text-center">
                    <x-mary-progress-radial :value="$emailSuccessRate" unit="%" class="text-success" style="--size: 8rem; --thickness: 8px;" />
                    <p class="mt-2 font-medium">Email<br>{{ $emailSuccessRate }}%</p>
                </div>
                <div class="text-center">
                    <x-mary-progress-radial :value="$whatsappSuccessRate" unit="%" class="text-success" style="--size: 8rem; --thickness: 8px;" />
                    <p class="mt-2 font-medium">WhatsApp<br>{{ $whatsappSuccessRate }}%</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-10 mt-10">
        <x-mary-card title="Últimos 3 Leads SyncFlow" subtitle="Leads recentes" shadow separator>
            @forelse($ultimosLeads as $lead)
                <x-mary-list-item :item="$lead">
                    <x-slot:value>
                        {{ $lead->contact_name ?? 'Lead sem nome' }}
                    </x-slot:value>
                    <x-slot:sub-value>
                        {{ $lead->situacao_contato }} - {{ $lead->formatted_created_at }}
                    </x-slot:sub-value>
                    <x-slot:actions>
                        <x-mary-button
                            icon="o-arrow-up-right"
                            @click="window.location.href = '{{ route('sync-flow.index', $lead->id) }}'"
                            class="btn-sm btn-primary"
                        />
                    </x-slot:actions>
                </x-mary-list-item>
            @empty
                <p class="text-center text-gray-500">Nenhum lead recente.</p>
            @endforelse
        </x-mary-card>

        <x-mary-card title="Últimos 3 Contatos" subtitle="Contatos recentes (com sync Chatwoot)" shadow separator>
            {{-- NOVO: Slot actions com botão de sync --}}
            <x-slot:actions>
                <x-mary-button
                    icon="o-bars-arrow-down"
                    label="Sync Chatwoot"
                    wire:click="syncContatosDashboard"
                    spinner="syncContatosDashboard"
                    class="btn-sm btn-primary"
                />
            </x-slot:actions>
            @forelse($ultimosContatos as $contato)
                <x-mary-list-item :item="$contato">
                    <x-slot:value>
                        {{ $contato->contact_name ?? 'Contato sem nome' }}
                    </x-slot:value>
                    <x-slot:sub-value>
                        {{ $contato->phone_number ?? $contato->contact_email ?? 'Sem dados' }} - {{ $contato->sync_info }} - {{ $contato->formatted_created_at }}
                    </x-slot:sub-value>
                    <x-slot:actions>
                        <x-mary-button
                            icon="o-arrow-up-right"
                            @click="window.location.href = '{{ route('contatos.index', $contato->id) }}'"
                            class="btn-sm btn-primary"
                        />
                    </x-slot:actions>
                </x-mary-list-item>
            @empty
                <p class="text-center text-gray-500">Nenhum contato recente. <button wire:click="syncContatosDashboard" class="link">Sincronize agora!</button></p>
            @endforelse
        </x-mary-card>

        <x-mary-card title="Cadências Ativas" subtitle="Com leads atribuídos" shadow separator>
            <x-mary-table
                :headers="$headersCadencias"
                :rows="$cadencias"
                striped
                class="bg-base-100"
            >
                @scope('actions', $cadencia)
                    <x-mary-button
                        icon="o-arrow-up-right"
                        @click="window.location.href = '{{ route('cadencias.index', $cadencia->id) }}'"
                        class="btn-sm btn-primary"
                    />
                @endscope
            </x-mary-table>
        </x-mary-card>
    </div>
</div>