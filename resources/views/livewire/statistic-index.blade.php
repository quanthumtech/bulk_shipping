<div>
    <x-mary-header
        title="Dashboard"
        subtitle="Visão geral de estatísticas e métricas de desempenho"
        separator />

    <div class="grid lg:grid-cols-4 gap-5">
        {{-- INFO: Mensagens --}}
        <x-mary-stat
            title="Messages"
            value="{{ $menssages }}"
            icon="o-envelope"
            tooltip="Hello"
            class="bg-gray-50 shadow-lg"/>

        {{-- INFO: Contatos --}}
        <x-mary-stat
            title="Contatos"
            description="Chatwoot"
            value="{{ count($contatos) }}"
            icon="o-user-circle"
            tooltip-bottom="There"
            class="bg-gray-50 shadow-lg"/>

        {{-- INFO: Grupos --}}
        <x-mary-stat
            title="Grupos"
            value="{{ $grupos }}"
            icon="o-rectangle-group"
            tooltip-left="Ops!"
            class="bg-gray-50 shadow-lg"/>

        {{-- INFO: Média de contatos por grupo --}}
        <x-mary-stat
            title="Média Contatos"
            description="Média contatos por grupo"
            value="{{ $media_contatos }}"
            icon="o-arrow-trending-up"
            tooltip-right="Gosh!"
            class="bg-gray-50 shadow-lg"/>
    </div>

    <div class="grid lg:grid-cols-2 gap-10 mt-10 justify-items-center">
        {{-- Gráfico de Contatos Durante o Mês --}}
        <div class="bg-white p-4 rounded-lg shadow-lg w-full max-w-md flex flex-col items-center">
            <h3 class="text-lg font-semibold mb-4 text-center">Contatos Durante o Mês</h3>
            <x-mary-chart wire:model="contatosChart" class="w-full h-72 mx-auto flex justify-center items-center" />
            </div>

        {{-- Gráfico de Frequência de Mensagens --}}
        <div class="bg-white p-4 rounded-lg shadow-lg w-full max-w-md flex flex-col items-center">
            <h3 class="text-lg font-semibold mb-4 text-center">Frequência de Mensagens</h3>
            <x-mary-chart wire:model="mensagensChart" class="w-full h-72 mx-auto flex justify-center items-center" /> <!-- Centralizando o gráfico -->
        </div>
    </div>

</div>
