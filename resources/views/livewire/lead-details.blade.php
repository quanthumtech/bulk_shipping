<div>
    <x-mary-header title="Detalhes do Lead: {{ $lead->contact_name ?? 'Não informado' }}" subtitle="Informações completas do lead, cadência e conversas associadas" separator>
        <x-slot:actions>
            <x-mary-button label="Voltar" icon="o-arrow-left" link="/sync-flow" />
        </x-slot:actions>
    </x-mary-header>

    <!-- Informações do Lead -->
    <div class="mt-4">
        <x-mary-card title="Informações do Lead" class="shadow-lg">
            @if($lead)
                <table class="table w-full">
                    <tbody>
                        <tr><th>Nome</th><td>{{ $lead->contact_name ?? 'Não informado' }}</td></tr>
                        <tr><th>Número de Contato</th><td>{{ $lead->contact_number ?? 'Não informado' }}</td></tr>
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
    </div>

    <!-- Informações da Cadência -->
    <div class="mt-4">
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
    </div>

    <!-- Conversas -->
    <div class="mt-4">
        <x-mary-card title="Conversas no Chatwoot" class="shadow-lg">
            @if(!empty($conversations))
                @foreach($conversations as $conversation)
                    <x-mary-collapse :open="$showMessages[$conversation['id']] ?? false" separator>
                        <x-slot:heading>
                            <div wire:click="toggleMessages({{ $conversation['id'] }})" class="cursor-pointer text-base-content">
                                Conversa ID: {{ $conversation['id'] }} (Status: {{ $conversation['status'] }})
                            </div>
                        </x-slot:heading>
                        <x-slot:content>
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
                            <h4 class="font-bold mb-2">Mensagens</h4>
                            @if(!empty($conversation['messages']))
                                <ul class="space-y-2">
                                    @foreach($conversation['messages'] as $message)
                                        <li class="border-b pb-2">
                                            <p class="text-sm text-gray-600">
                                                {{ $message['created_at'] }} - {{ $message['sender_name'] }}
                                                @if($message['message_type'] == 2)
                                                    <x-mary-badge value="Sistema" class="badge-secondary" />
                                                @elseif($message['sender_type'] == 'User')
                                                    <x-mary-badge value="Usuário" class="badge-primary" />
                                                @else
                                                    <x-mary-badge value="Outro" class="badge-info" />
                                                @endif
                                            </p>
                                            <p class="text-base">{{ $message['content'] }}</p>
                                            <p class="text-xs text-gray-400">Mensagem ID: {{ $message['message_id'] }}</p>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-gray-500">Nenhuma mensagem encontrada.</p>
                            @endif
                        </x-slot:content>
                    </x-mary-collapse>
                @endforeach
            @else
                <p class="text-gray-500">Nenhuma conversa encontrada.</p>
            @endif
        </x-mary-card>
    </div>
</div>