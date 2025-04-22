<div>
    <x-mary-header title="Histórico de Conversas" subtitle="Visualize todas as conversas do lead {{ $lead->contact_name ?? 'Não definido' }}" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-button label="Voltar" icon="o-arrow-left" link="{{ route('sync-flow.index') }}" class="btn-outline" />
        </x-slot:middle>
    </x-mary-header>

    <div class="container mx-auto p-4">
        @if (empty($conversations))
            <div class="text-center text-gray-500 py-10">
                <p>Nenhuma conversa encontrada para este lead.</p>
            </div>
        @else
            <div class="space-y-8">
                @foreach ($conversations as $conversation)
                    <div class="bg-base-100 shadow-lg rounded-lg p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Conversa #{{ $conversation['id'] }} (Agente: {{ $conversation['agent_name'] }})</h3>
                            <span class="text-sm text-gray-500">Última atividade: {{ $conversation['last_activity_at'] }}</span>
                        </div>
                        <div class="max-h-96 overflow-y-auto space-y-4 p-4 bg-gray-50 rounded-lg">
                            @foreach ($conversation['messages'] as $message)
                                <div class="{{ $message['is_sent'] ? 'flex justify-end' : 'flex justify-start' }}">
                                    <div class="{{ $message['is_sent'] ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800' }} rounded-lg p-3 max-w-xs">
                                        <p class="text-xs font-semibold">{{ $message['sender_name'] }}</p>
                                        <p class="text-sm mt-1">{{ $message['content'] }}</p>
                                        <p class="text-xs mt-1 opacity-70">{{ $message['created_at'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
