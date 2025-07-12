<div>
    <x-mary-header
        title="Logs de Processos"
        subtitle="Visualize os logs gerados pelos processos do sistema."
        separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-magnifying-glass" wire:model.live="search" placeholder="Pesquisar por mensagem..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button icon="o-funnel" wire:click="openFilterDrawer" title="Filtros" />
            @if(!empty($selectedLogs))
                <x-mary-button icon="o-archive-box" label="Arquivar Selecionados" wire:click="archiveSelected" class="btn-primary" />
            @endif
        </x-slot:actions>
    </x-mary-header>

    <x-mary-table
        :headers="$headers"
        :rows="$logs"
        striped
        class="bg-base-100"
        with-pagination
        per-page="perPage"
        :per-page-values="[5, 10, 20]"
        wire:key="logs-table">
        @scope('header_checkbox', $header)
            <x-mary-checkbox wire:model="selectedLogs" value="all" />
        @endscope
        @scope('header_type', $header)
            <h3 class="text-xl font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope
        @scope('header_message', $header)
            <h3 class="text-xl font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope
        @scope('header_created_at', $header)
            <h3 class="text-xl font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope
        @scope('cell_checkbox', $log)
            <x-mary-checkbox wire:model="selectedLogs" :value="$log->id" />
        @endscope
        @scope('cell_type', $log)
            <span class="{{ $log->type === 'error' ? 'text-red-500' : ($log->type === 'warning' ? 'text-yellow-500' : 'text-green-500') }}">
                {{ $log->type === 'info' ? 'Info' : ($log->type === 'warning' ? 'Aviso' : 'Erro') }}
            </span>
        @endscope
        @scope('cell_message', $log)
            <div class="truncate max-w-md" title="{{ $log->message }}">{{ $log->message }}</div>
        @endscope
        @scope('cell_created_at', $log)
            {{ \Carbon\Carbon::parse($log->created_at)->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}
        @endscope
        @scope('actions', $log)
            <div class="flex space-x-2">
                <x-mary-button
                    icon="o-eye"
                    class="btn-sm btn-primary"
                    wire:click="openModal({{ $log->id }})" />
                @if(!$log->archived)
                    <x-mary-button
                        icon="o-archive-box"
                        class="btn-sm btn-warning"
                        wire:click="archiveLog({{ $log->id }})" />
                @endif
            </div>
        @endscope
    </x-mary-table>

    <!-- Filter Drawer -->
    <x-mary-drawer
        wire:model="showFilterDrawer"
        title="Filtros de Logs"
        subtitle="Ajuste os filtros para personalizar a visualização dos logs. Use 'Data do Log' para filtrar por data e hora exata ou 'Data Inicial/Final' para um intervalo."
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
        right>
        <div class="space-y-4">
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
            <x-mary-input
                label="Data e Hora do Log"
                type="datetime-local"
                wire:model.live="dataNow"
                placeholder="Selecione data e hora exata" />
            <x-mary-select
                label="Tipo de Log"
                :options="$typeOptions"
                option-label="name"
                option-value="id"
                wire:model.live="typeFilter"
                placeholder="Selecione o tipo" />
            <x-mary-select
                label="Status de Arquivamento"
                :options="$archivedOptions"
                option-label="name"
                option-value="id"
                wire:model.live="archivedFilter"
                placeholder="Selecione o status" />
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

    <!-- Log Details Drawer -->
    <x-mary-drawer
        wire:model="showDrawer"
        title="Detalhes do Log"
        subtitle=""
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
        right>
        @if($selectedLog)
            <div class="space-y-4">
                <div><strong>Tipo:</strong> {{ $selectedLog->type === 'info' ? 'Info' : ($selectedLog->type === 'warning' ? 'Aviso' : 'Erro') }}</div>
                <div><strong>Mensagem:</strong> {{ $selectedLog->message }}</div>
                <div><strong>Data:</strong> {{ \Carbon\Carbon::parse($selectedLog->created_at)->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}</div>
                <div><strong>Status:</strong> {{ $selectedLog->archived ? 'Arquivado' : 'Ativo' }}</div>
                <div><strong>Contexto:</strong>
                    <pre class="bg-gray-100 dark:bg-gray-800 p-2 rounded text-gray-800 dark:text-gray-100">{{ json_encode($selectedLog->context, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            <x-slot:actions>
                @if(!$selectedLog->archived)
                    <x-mary-button
                        label="Arquivar"
                        icon="o-archive-box"
                        class="btn-warning"
                        wire:click="archiveLog({{ $selectedLog->id }})" />
                @endif
                <x-mary-button label="Fechar" wire:click="closeModal" />
            </x-slot:actions>
        @else
            <div class="text-gray-500 dark:text-gray-400">Nenhum log selecionado.</div>
        @endif
    </x-mary-drawer>
</div>