<div>
    <x-mary-header
        title="FAQ"
        subtitle="Descubra respostas e dicas para aproveitar ao máximo as funcionalidades do BulkShip"
        separator />

    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
            <h3 class="text-xl font-bold text-black">Perguntas Frequentes</h3>
            <p class="text-gray-500">Encontre respostas para as perguntas mais comuns.</p>
        </div>

        <!-- Collapse para Gerenciar Mensagens -->
        <x-mary-collapse :open="$show['gerenciar_mensagens']" separator>
            <x-slot:heading>
                <div wire:click="toggleCollapse('gerenciar_mensagens')" class="cursor-pointer">
                    Módulo Gerenciar Mensagens
                </div>
            </x-slot:heading>
            <x-slot:content>
                <p class="text-gray-500 mt-2">
                    O módulo de Gerenciar Mensagens permite que você crie e envie mensagens
                    personalizadas para seus contatos de forma eficiente.
                </p>
                <p class="text-gray-500 mt-2">
                    Você pode criar grupos, dentro do grupo enviar mensagens para os contatos desse grupo.
                </p>
                <p class="text-gray-500 mt-2">
                    Os contatos são importatos do Chatwoot, mas você pode adicionar novos contatos manualmente.
                    Assim que você adicionar um novo contato, ele será adicionado ao grupo.
                </p>
            </x-slot:content>
        </x-mary-collapse>

        <!-- Collapse para Outro Módulo -->
        <x-mary-collapse :open="$show['cadencia']" separator>
            <x-slot:heading>
                <div wire:click="toggleCollapse('cadencia')" class="cursor-pointer">
                    Cadência
                </div>
            </x-slot:heading>
            <x-slot:content>
                <p class="text-gray-500 mt-2">
                    Neste modulo você pode criar cadências personalizadas. Como criar um fluxo de mensagens atraves de
                    mensagens automáticas?
                </p>
                <p class="text-gray-500 mt-2">
                    Você pode criar um fluxo de mensagens automáticas, atraves de das etapas das cadêcnias, assim cada etapa
                    pode ser uma mensagem diferente, e você pode adicionar um tempo de espera entre cada etapa.
                </p>
            </x-slot:content>
        </x-mary-collapse>

        <!-- Seção de Dicas -->
        <x-mary-card class="bg-white">
            <div class="flex flex-col gap-2">
                <h3 class="text-xl font-bold text-black">Dicas</h3>
                <p class="text-gray-500">Explore dicas úteis para otimizar sua experiência.</p>
            </div>
        </x-mary-card>
    </div>
</div>
