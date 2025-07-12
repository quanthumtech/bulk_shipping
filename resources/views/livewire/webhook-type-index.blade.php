<div>
    <x-mary-header
        title="Gerenciar Webhook's"
        subtitle="Escolha um webhook para visualizar seus logs."
        separator />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        @foreach($webhookTypeOptions as $option)
            <x-mary-card
                class="bg-base-100 shadow-lg"
                title="{{ $option['name'] }} {{ $option['id'] ? 'Webhook' : 'Webhooks' }}"
                subtitle="Exibir logs {{ $option['id'] ? 'do ' . $option['name'] : 'de todos os webhooks' }}"
                separator>
                <a href="{{ route('webhook-logs.index', ['userId' => null, 'webhookType' => $option['id']]) }}">
                    <x-mary-button label="Selecionar" class="btn-primary" />
                </a>
            </x-mary-card>
        @endforeach
    </div>
</div>