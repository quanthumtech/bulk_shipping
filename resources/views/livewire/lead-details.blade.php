@php
    use Carbon\Carbon;
@endphp
<div>
    <x-mary-header title="Detalhes do Lead: {{ $lead->contact_name ?? 'Não informado' }}" subtitle="Informações completas do lead, cadência, conversas e logs associados" separator>
        <x-slot:actions>
            <x-mary-button label="Voltar" icon="o-arrow-left" link="/sync-flow" />
        </x-slot:actions>
    </x-mary-header>

    <!-- Tabs -->
    <x-mary-tabs wire:model="activeTab" class="mt-4">
        <!-- Informações do Lead -->
        <x-mary-tab name="lead_info" label="Informações do Lead">
            <x-mary-card title="Informações do Lead" class="shadow-lg">
                @if($lead)
                    <table class="table w-full">
                        <tbody>
                            <tr><th>Cadastrado Em</th><td>{{ $lead->created_at->format('d/m/Y H:i:s') ?? 'Não informado' }}</td></tr>
                            <tr><th>Atualizado Em</th><td>{{ $lead->updated_at->format('d/m/Y H:i:s') ?? 'Não informado' }}</td></tr>
                            <tr><th>Nome</th><td>{{ $lead->contact_name ?? 'Não informado' }}</td></tr>
                            <tr>
                                <th>Número de Contato</th>
                                <td>
                                    {{ $lead->contact_number ?? 'Não informado' }}
                                    @if($lead->is_whatsapp)
                                        <x-mary-button class="btn-primary btn-xs ml-2" wire:click="openWhatsappModal" title="Número validado como WhatsApp">
                                            <x-mary-icon name="o-check-circle" class="w-4 h-4" />
                                            <span class="ml-1 text-xs">WhatsApp</span>
                                        </x-mary-button>
                                    @else
                                        <x-mary-button class="btn-error btn-xs ml-2" wire:click="openWhatsappModal" title="Clique para testar/verificar WhatsApp">
                                            <x-mary-icon name="o-x-circle" class="w-4 h-4 text-error" />
                                            <span class="ml-1 text-xs text-error">Não WhatsApp</span>
                                        </x-mary-button>
                                    @endif
                                </td>
                            </tr>
                            <tr><th>Número da Empresa</th><td>{{ $lead->contact_number_empresa ?? 'Não informado' }}</td></tr>
                            <tr><th>Email</th><td>{{ $lead->contact_email ?? 'Não informado' }}</td></tr>
                            <tr><th>Estágio</th><td>{{ $lead->estagio ?? 'Não informado' }}</td></tr>
                            <tr><th>Situação de Contato</th><td>{{ $lead->situacao_contato ?? 'Não informado' }}</td></tr>
                            <tr><th>Nome do Vendedor</th><td>{{ $lead->nome_vendedor ?? 'Não informado' }}</td></tr>
                            <tr><th>Email do Vendedor</th><td>{{ $lead->email_vendedor ?? 'Não informado' }}</td></tr>
                            <tr><th>ID Card</th><td>{{ $lead->id_card ?? 'Não informado' }}</td></tr>
                            <tr><th>Contact ID</th><td>{{ $lead->contact_id ?? 'Não informado' }}</td></tr>
                            <tr><th>Origem</th><td>{{ $isFromWebhook ? 'Webhook' : 'Manual' }}</td></tr>
                            <tr><th>Status Chatwoot</th><td>
                                @if($lead->chatwoot_status)
                                    <x-mary-badge value="{{ $lead->chatwoot_status }}" class="badge-primary" />
                                @else
                                    <x-mary-badge value="Não informado" class="badge-secondary" />
                                @endif
                            </td></tr>
                        </tbody>
                    </table>
                @else
                    <p class="text-gray-500">Nenhuma informação de lead disponível.</p>
                @endif
            </x-mary-card>
        </x-mary-tab>

        <!-- Cadência Atribuída -->
        <x-mary-tab name="cadencia" label="Cadência Atribuída">
            <x-mary-card title="Cadência Atribuída" class="shadow-lg">
                @if($cadencia)
                    <table class="table w-full">
                        <tbody>
                            <tr><th>Nome</th><td>{{ $cadencia->name ?? 'Não informado' }}</td></tr>
                            <tr><th>Descrição</th><td>{{ $cadencia->description ?? 'Não informado' }}</td></tr>
                            <tr><th>Horário Início</th><td>{{ $cadencia->hora_inicio ?? 'Não informado' }}</td></tr>
                            <tr><th>Horário Fim</th><td>{{ $cadencia->hora_fim ?? 'Não informado' }}</td></tr>
                            <tr><th>Ativo</th><td>{{ $cadencia->active ? 'Sim' : 'Não' }}</td></tr>
                            <tr><th>Etapas</th><td>{{ $cadencia->etapas->count() ?? 'Nenhuma etapa' }}</td></tr>
                        </tbody>
                    </table>
                @else
                    <p class="text-gray-500">Nenhuma cadência atribuída.</p>
                @endif
            </x-mary-card>
        </x-mary-tab>

        <!-- Conversas no Chatwoot -->
        <x-mary-tab name="conversations" label="Conversas no Chatwoot">
            <x-mary-card title="Conversas no Chatwoot" class="shadow-lg">
                @if(!empty($conversations))
                    @foreach($conversations as $conversation)
                        <div class="border-b pb-4 mb-4">
                            <table class="table w-full mb-4">
                                <thead>
                                    <tr>
                                        <th>ID da Conversa</th>
                                        <th>Status</th>
                                        <th>Agente Atribuído</th>
                                        <th>Email do Agente</th>
                                        <th>Criado em</th>
                                        <th>Atualizado em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ $conversation['id'] }}</td>
                                        <td>{{ $conversation['status'] }}</td>
                                        <td>{{ $conversation['assignee_name'] }}</td>
                                        <td>{{ $conversation['assignee_email'] ?? 'Não informado' }}</td>
                                        <td>{{ \Carbon\Carbon::parse($conversation['created_at'])->format('d/m/Y H:i') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($conversation['updated_at'])->format('d/m/Y H:i') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <a href="https://chatwoot.plataformamundo.com.br/app/accounts/{{ auth()->user()->chatwoot_accoumts }}/conversations/{{ $conversation['id'] }}" target="_blank" class="btn btn-primary btn-sm">Ver Conversa</a>
                        </div>
                    @endforeach
                @else
                    <p class="text-gray-500">Nenhuma conversa encontrada.</p>
                @endif
            </x-mary-card>
        </x-mary-tab>

        <!-- Logs -->
        <x-mary-tab name="logs" label="Logs">
            <x-mary-card title="Logs" class="shadow-lg">
                @if(!empty($logs))
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tipo</th>
                                <th>Webhook</th>
                                <th>Mensagem</th>
                                <th>Conta Chatwoot</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr>
                                    <td>{{ $log['id'] }}</td>
                                    <td>
                                        @if($log['type'] === 'info')
                                            <x-mary-badge value="Info" class="badge-info" />
                                        @elseif($log['type'] === 'warning')
                                            <x-mary-badge value="Aviso" class="badge-warning" />
                                        @elseif($log['type'] === 'error')
                                            <x-mary-badge value="Erro" class="badge-error" />
                                        @else
                                            <x-mary-badge value="{{ $log['type'] }}" class="badge-secondary" />
                                        @endif
                                    </td>
                                    <td>{{ $log['webhook_type'] }}</td>
                                    <td>{{ Str::limit($log['message'], 50) }}</td>
                                    <td>{{ $log['chatwoot_account_id'] }}</td>
                                    <td>{{ $log['created_at'] }}</td>
                                    <td>
                                        <x-mary-button label="Detalhes" icon="o-eye" class="btn-sm" @click="$wire.openLogModal({{ $log['id'] }})" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-gray-500">Nenhum log encontrado.</p>
                @endif
            </x-mary-card>
        </x-mary-tab>
    </x-mary-tabs>

    <!-- Modal para Detalhes do Log -->
    <x-mary-drawer
        wire:model="showLogDrawer"
        title="Detalhes do Log"
        subtitle="Informações completas do log selecionado"
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/2"
        right>
        @if($selectedLog)
            <div class="space-y-4">
                <x-mary-input label="ID" value="{{ $selectedLog->id }}" readonly />
                <x-mary-input label="Tipo" value="{{ ucfirst($selectedLog->type) }}" readonly />
                <x-mary-input label="Webhook Type" value="{{ $selectedLog->webhook_type ?? 'N/A' }}" readonly />
                <x-mary-input label="Mensagem" value="{{ $selectedLog->message }}" readonly />
                <x-mary-input label="Conta Chatwoot" value="{{ $selectedLog->chatwoot_account_id ?? 'N/A' }}" readonly />
                <x-mary-input label="Data" value="{{ Carbon::parse($selectedLog->created_at)->format('d/m/Y H:i:s') }}" readonly />
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
            <p class="text-gray-500">Nenhum log selecionado.</p>
        @endif
    </x-mary-drawer>

    <!-- Novo Modal para Verificação WhatsApp -->
    <x-mary-modal wire:model="showWhatsappModal" title="Verificação WhatsApp" box-class="max-w-md">
        <div class="space-y-4">
            <p class="text-sm text-gray-600">
                O número do lead foi verificado usando a <a href="https://doc.evolution-api.com/v2/api-reference/chat-controller/check-is-whatsapp" target="_blank" class="text-blue-600 underline">API da Evolution</a>. 
                Essa verificação confirma se o número está registrado no WhatsApp.
            </p>

            @if($testResult === null)
                <div class="text-center text-gray-500 py-4">Digite um número para testar.</div>
            @elseif($testResult === 'valid')
                <div class="text-center text-green-600 py-4">
                    <x-mary-icon name="o-check-circle" class="w-8 h-8 mx-auto mb-2" />
                    <p>Número é WhatsApp válido!</p>
                </div>
            @elseif($testResult === 'invalid')
                <div class="text-center text-red-600 py-4">
                    <x-mary-icon name="o-x-circle" class="w-8 h-8 mx-auto mb-2" />
                    <p>Número não é WhatsApp válido.</p>
                </div>
            @endif

            <div class="space-y-2">
                <x-mary-input 
                    label="Número para Testar (ex: 5511999999999)" 
                    type="tel" 
                    placeholder="+5511999999999" 
                    wire:model.live="testNumber" 
                />
                <x-mary-button 
                    label="Testar Número" 
                    class="btn-primary w-full" 
                    wire:click="testWhatsappNumber" 
                    spinner="testWhatsappNumber" 
                    :disabled="empty($testNumber)"
                />
            </div>
        </div>
    </x-mary-modal>
</div>