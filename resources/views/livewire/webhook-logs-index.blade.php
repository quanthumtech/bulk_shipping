<div>
    <x-mary-header
        title="{{ $userId ? 'Logs do Usuário' : 'Logs de Webhooks' }} {{ $webhookTypeFilter ? ' - ' . ucfirst($webhookTypeFilter) : '' }}"
        subtitle="Visualize os logs gerados pelos webhooks."
        separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-magnifying-glass" wire:model.live="search" placeholder="Pesquisar qualquer coisa..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button icon="o-funnel" wire:click="openFilterDrawer" title="Filtros" />
            <a href="{{ route('webhook-types.index') }}">
                <x-mary-button icon="o-arrow-left" title="Voltar para Tipos de Webhook" />
            </a>
        </x-slot:actions>
    </x-mary-header>

    @if(!empty($selected))
        <div class="flex space-x-2 mb-4">
            <x-mary-button
                label="Excluir Selecionados"
                icon="o-trash"
                class="btn-error"
                wire:click="deleteSelected"
                spinner
                wire:loading.attr="disabled" />
            <x-mary-button
                label="Arquivar Selecionados"
                icon="o-archive-box"
                class="btn-warning"
                wire:click="archiveSelected"
                spinner
                wire:loading.attr="disabled" />
            <x-mary-button
                label="Exportar Selecionados"
                icon="o-document-arrow-down"
                class="btn-primary"
                wire:click="exportSelected"
                spinner
                wire:loading.attr="disabled" />
        </div>
    @endif

    <x-mary-table
        :headers="$headers"
        :rows="$logs"
        striped
        class="bg-base-100"
        with-pagination
        per-page="perPage"
        :per-page-values="[5, 10, 20]"
        selectable
        wire:model.live="selected"
        wire:key="logs-table">
        @scope('header_type', $header)
            <h3 class="text-xl font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope
        @scope('header_webhook_type', $header)
            <h3 class="text-xl font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope
        @scope('header_message', $header)
            <h3 class="text-xl font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope
        @scope('header_chatwoot_account_id', $header)
            <h3 class="text-xl font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope
        @scope('header_created_at', $header)
            <h3 class="text-xl font-bold text-base-content">{{ $header['label'] }}</h3>
        @endscope
        @scope('cell_type', $log)
            <span class="{{ $log->type === 'error' ? 'text-red-500' : ($log->type === 'warning' ? 'text-yellow-500' : 'text-green-500') }}">
                {{ $log->type === 'info' ? 'Info' : ($log->type === 'warning' ? 'Aviso' : 'Erro') }}
            </span>
        @endscope
        @scope('cell_webhook_type', $log)
            {{ $log->webhook_type ? ucfirst($log->webhook_type) : 'N/A' }}
        @endscope
        @scope('cell_created_at', $log)
            {{ $log->created_at->format('d/m/Y H:i:s') }}
        @endscope
        @scope('cell_message', $log)
            <div class="truncate max-w-md" title="{{ $log->message }}">{{ $log->message }}</div>
        @endscope
        @scope('actions', $log)
            <div class="flex space-x-2">
                <x-mary-button
                    icon="o-eye"
                    class="btn-sm btn-primary"
                    wire:click="openModal({{ $log->id }})" />
                <x-mary-button
                    icon="o-trash"
                    class="btn-sm btn-error"
                    wire:click="deleteLog({{ $log->id }})"
                    title="Excluir Log" />
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
                label="Tipo de Webhook"
                :options="$webhookTypeOptions"
                option-label="name"
                option-value="id"
                wire:model.live="webhookTypeFilter"
                placeholder="Selecione o webhook" />
            <x-mary-checkbox
                label="Mostrar Logs Arquivados"
                wire:model.live="showArchived" />
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
        class="w-11/12 lg:w-1/2"
        right>
        @if($selectedLog)
            <div class="space-y-4">
                <div><strong>Tipo:</strong> {{ $selectedLog->type === 'info' ? 'Info' : ($selectedLog->type === 'warning' ? 'Aviso' : 'Erro') }}</div>
                <div><strong>Webhook:</strong> {{ $selectedLog->webhook_type ? ucfirst($selectedLog->webhook_type) : 'N/A' }}</div>
                <div><strong>Mensagem:</strong> {{ $selectedLog->message }}</div>
                <div><strong>Conta Chatwoot:</strong> {{ $selectedLog->chatwoot_account_id ?? 'N/A' }}</div>
                <div><strong>Data:</strong> {{ $selectedLog->created_at->format('d/m/Y H:i:s') }}</div>
                <div>
                    <strong>Contexto:</strong>
                    <div class="relative">
                        <button
                            type="button"
                            class="absolute top-2 right-2 z-10 btn btn-xs btn-primary"
                            onclick="navigator.clipboard.writeText(document.getElementById('context-json').innerText)">
                            Copiar JSON
                        </button>
                        <pre
                            id="context-json"
                            class="bg-gray-100 dark:bg-gray-800 p-4 rounded text-gray-800 dark:text-gray-100 overflow-auto text-sm leading-relaxed border border-gray-200 dark:border-gray-700"
                            style="white-space: pre-wrap; word-break: break-all;"
                        >{{ json_encode($selectedLog->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            </div>
        @else
            <div class="text-gray-500 dark:text-gray-400">Nenhum log selecionado.</div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Fechar" wire:click="closeModal" />
        </x-slot:actions>
    </x-mary-drawer>
</div>