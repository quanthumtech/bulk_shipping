<div class="container mx-auto p-6">
    <x-mary-header title="{{ $title }}" subtitle="Configure sua cadência de comunicação" separator>
        <x-slot:actions>
            <x-mary-button label="Voltar" icon="o-arrow-left" class="btn-outline" link="{{ route('cadencias.index') }}" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <x-mary-input label="Nome da cadência" wire:model.live="form.name" placeholder="Digite aqui o nome da cadência..." required />
            </div>
            @if(Auth::user()->chatwoot_accounts == 5)
                <div class="space-y-2">
                    <x-mary-select label="Escolha o Estágio" :options="$options" wire:model.live="form.stage" />
                </div>
            @endif
        </div>

        <x-mary-alert
            title="Range"
            description="Insira o intervalo da cadência, dentro do horário comercial."
            icon="o-exclamation-triangle"
            class="bg-warning/10 text-warning border-warning/20"
            dismissible
        />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <x-mary-datetime label="Hora Início" wire:model.live="form.hora_inicio" icon="o-clock" type="time" required />
            </div>
            <div class="space-y-2">
                <x-mary-datetime label="Hora Fim" wire:model.live="form.hora_fim" icon="o-clock" type="time" required />
            </div>
        </div>

        <x-mary-textarea
            label="Descrição"
            wire:model.live="form.description"
            placeholder="Descreva a cadência..."
            hint="Máximo 1000 caracteres"
            rows="5"
            inline
        />

        <x-mary-select
            label="Selecione a Caixa"
            wire:model.live="form.evolution_id"
            hint="Escolha a caixa que será responsável por enviar as mensagens."
            :options="$caixasEvolution"
            required
        />

        <x-mary-toggle label="Ativo" wire:model.live="form.active" />

        <x-mary-header title="Dias da Semana" subtitle="Selecione os dias em que a cadência pode ser executada" />

        <div class="flex flex-wrap gap-2">
            @foreach ([1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'] as $day => $label)
                <x-mary-button
                    :label="$label"
                    :class="in_array($day, $form->days_of_week) ? 'btn-primary' : 'btn-outline'"
                    wire:click="toggleDay({{ $day }})"
                />
            @endforeach
        </div>

        <x-mary-header title="Datas Excluídas" subtitle="Selecione datas específicas (ex.: feriados) para excluir da cadência" />

        <x-mary-datepicker
            label="Datas Excluídas"
            wire:model.live="form.excluded_dates"
            :config="$datepickerConfig"
            hint="Clique nas datas para selecionar/excluir. Suporta múltiplas seleções."
            class="mb-4"
        />

        <div class="flex justify-end space-x-2">
            <x-mary-button label="Cancelar" class="btn-outline" link="{{ route('cadencias.index') }}" />
            <x-mary-button label="Salvar" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
        </div>
    </x-mary-form>
</div>