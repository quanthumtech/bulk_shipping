<div class="container mx-auto p-6">
    <x-mary-header title="{{ $title }}" subtitle="Configure sua cadência de comunicação" separator>
        <x-slot:actions>
            <x-mary-button label="Voltar" icon="o-arrow-left" class="btn-outline" link="{{ route('cadencias.index') }}" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <x-mary-input label="Nome da Cadência" wire:model.live="form.name" placeholder="Ex: Cadência de Onboarding" required hint="Nome curto e descritivo" />
            </div>
            @if(Auth::user()->chatwoot_accoumts == 5)
                <div class="space-y-2">
                    <x-mary-select label="Estágio no Zoho" :options="$options" wire:model.live="form.stage" hint="Opcional: Vincule a um estágio do CRM" />
                </div>
            @endif
        </div>
        <x-mary-textarea
            label="Descrição"
            wire:model.live="form.description"
            placeholder="Descreva o objetivo da cadência... Ex: Sequência de 5 mensagens para leads qualificados."
            hint="Máx. 1000 caracteres. Ajuda na organização."
            rows="3"
        />

        {{-- Seção 2: Horários e Dias --}}
        <x-mary-header title="Horários e Disponibilidade" subtitle="Defina quando a cadência pode rodar" />
        <x-mary-alert
            title="Dica de Horário"
            description="Escolha um range comercial para evitar envios noturnos."
            icon="o-clock"
            class="bg-info/10 text-info border-info/20"
            dismissible
        />
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <x-mary-datetime label="Hora Início" wire:model.live="form.hora_inicio" icon="o-clock" type="time" required hint="Ex: 08:00" />
            </div>
            <div class="space-y-2">
                <x-mary-datetime label="Hora Fim" wire:model.live="form.hora_fim" icon="o-clock" type="time" required hint="Ex: 18:00 (deve ser após início)" />
            </div>
        </div>

        <x-mary-select
            label="Caixa Responsável"
            wire:model.live="form.evolution_id"
            hint="Escolha a caixa do Chatwoot para envios."
            :options="$caixasEvolution"
            required
        />

        <x-mary-header title="Dias da Semana" subtitle="Dias permitidos para execução" />
        <div class="flex flex-wrap gap-2">
            @foreach ([1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'] as $day => $label)
                <x-mary-button
                    :label="$label"
                    :class="in_array($day, $form->days_of_week) ? 'btn-primary' : 'btn-outline'"
                    wire:click="toggleDay({{ $day }})"
                />
            @endforeach
        </div>

        <x-mary-header title="Exceções" subtitle="Datas para pular (ex: feriados)" />
        <x-mary-datepicker
            label="Datas Excluídas"
            wire:model.live="form.excluded_dates"
            :config="$datepickerConfig"
            hint="Selecione múltiplas datas. Formato: DD/MM/YYYY."
            class="mb-4"
        />

        <x-mary-toggle label="Cadência Ativa?" wire:model.live="form.active" hint="Desative para pausar sem deletar." />

        {{-- Resumo Preview --}}
        <x-mary-card title="Resumo da Cadência" class="bg-blue-50">
            <p><strong>Nome:</strong> {{ $form->name ?? 'Não definido' }}</p>
            <p><strong>Horário:</strong> {{ $form->hora_inicio ?? '' }} às {{ $form->hora_fim ?? '' }}</p>
            <p><strong>Dias:</strong> {{ implode(', ', array_map(fn($d) => ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'][$d-1] ?? '', $form->days_of_week ?? [])) }}</p>
            <p><strong>Caixa:</strong> {{ $caixasEvolution->firstWhere('id', $form->evolution_id)['name'] ?? 'Não selecionada' }}</p>
        </x-mary-card>

        <div class="flex justify-end space-x-2">
            <x-mary-button label="Cancelar" class="btn-outline" link="{{ route('cadencias.index') }}" />
            <x-mary-button label="Salvar Cadência" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
        </div>
    </x-mary-form>
</div>