<div wire:poll.10s="loadMessages">
    <x-mary-header title="Chat Bulkship" subtitle="Escolha sua caixa de WhatsApp e envie mensagens no nosso chat" separator />

    @if($evolutions->isEmpty())
        <x-mary-alert title="Nenhuma instância ativa" class="alert-error">
            Nenhuma instância Evolution ativa encontrada. Configure uma instância ativa para continuar.
        </x-mary-alert>
    @else
        <div class="flex flex-col md:flex-row gap-4 h-[calc(100vh-200px)] w-full max-w-full box-border">
            <!-- Sidebar: Seleção de instância e lista de chats -->
            <div class="w-full md:w-1/3 bg-white p-4 rounded shadow overflow-y-auto box-border">
                <x-mary-select 
                    label="Instância WhatsApp" 
                    wire:model="selectedEvolutionId" 
                    :options="$evolutionOptions"
                />

                <x-mary-input 
                    label="Buscar chat" 
                    icon="o-magnifying-glass" 
                    wire:model.live.debounce.300ms="search" 
                />

                <ul class="space-y-2 w-full">
                    @foreach($chats as $chat)
                        <li 
                            wire:click="selectChat('{{ $chat['id'] }}')" 
                            class="cursor-pointer p-3 hover:bg-gray-100 rounded {{ $selectedChat && $selectedChat['id'] === $chat['id'] ? 'bg-gray-200' : '' }}"
                        >
                            <div class="font-bold text-sm">{{ $chat['name'] ?? str_replace('@c.us', '', $chat['id']) }}</div>
                            <div class="text-xs text-gray-500 truncate">
                                {{ isset($chat['lastMessage']['message']['content']) ? \Illuminate\Support\Str::limit($chat['lastMessage']['message']['content'], 30) : '' }}
                            </div>
                        </li>
                    @endforeach
                    @if(empty($chats))
                        <li class="text-gray-500">Nenhum chat encontrado.</li>
                    @endif
                </ul>
            </div>

            <!-- Chat Area -->
            <div class="w-full md:w-2/3 bg-white p-4 rounded shadow flex flex-col h-full box-border max-w-full">
                @if($selectedChat)
                    <div class="font-bold mb-4 text-lg !w-full max-w-full">
                        {{ $selectedChat['name'] ?? str_replace('@c.us', '', $selectedChat['id']) }}
                    </div>

                    <div class="flex-1 overflow-y-auto mb-4 space-y-3 !w-full max-w-full box-border relative" wire:loading.class="opacity-50">
                        <div wire:loading wire:target="loadMessages,send" class="absolute inset-0 flex justify-center items-center bg-white bg-opacity-50">
                            <x-mary-loading class="flex justify-center items-center" />
                        </div>
                        @foreach($messages as $message)
                            @php
                                $fromMe = $message['key']['fromMe'] ?? false;
                                $messageClass = $fromMe ? 'ml-auto bg-blue-100' : 'mr-auto bg-gray-100';
                            @endphp
                            <div class="{{ $messageClass }} p-3 rounded max-w-[70%] w-fit">
                                <div class="text-sm">
                                    {{ $message['message']['content'] ?? '[Mensagem não suportada]' }}
                                    @if(isset($message['message']['raw']['imageMessage']['url']))
                                        <img src="{{ $message['message']['raw']['imageMessage']['url'] }}" alt="Imagem" class="mt-2 max-w-full h-auto rounded">
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ isset($message['messageTimestamp']) ? \Carbon\Carbon::createFromTimestamp($message['messageTimestamp'])->format('d/m/Y H:i') : '' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <form wire:submit.prevent="send" class="flex flex-row gap-2 items-center w-full max-w-full box-border">
                        <div class="flex-1">
                            <x-mary-input 
                                wire:model.live.debounce.300ms="newMessage" 
                                placeholder="Digite sua mensagem..." 
                                class="w-full"
                                input-class="w-full h-14"
                            />
                        </div>
                        <x-mary-button 
                            type="submit" 
                            icon="o-paper-airplane" 
                            class="btn-primary flex-shrink-0 h-14 px-4"
                            style="min-width: 44px;"
                        />
                    </form>
                @else
                    <div class="flex-1 flex items-center justify-center text-gray-500 !w-full max-w-full">
                        Selecione um chat para começar.
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>