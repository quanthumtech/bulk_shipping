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

    <div class="grid lg:grid-cols-2 gap-10 mt-10">
        {{-- Gráfico de Contatos Durante o Mês --}}
        <div class="bg-white p-5 rounded-lg shadow-lg">
            <h3 class="text-lg font-semibold mb-5">Contatos Durante o Mês</h3>
            <x-mary-chart wire:model="contatosChart" />
        </div>

        {{-- Gráfico de Frequência de Mensagens --}}
        <div class="bg-white p-5 rounded-lg shadow-lg">
            <h3 class="text-lg font-semibold mb-5">Frequência de Mensagens</h3>
            <x-mary-chart wire:model="mensagensChart" />
        </div>
    </div>
</div>
