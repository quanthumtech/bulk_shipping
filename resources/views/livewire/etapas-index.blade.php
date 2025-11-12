<div class="container mx-auto p-6">
    <x-mary-header title="Etapas da Cadência: {{ $cadencia->name }}" subtitle="Adicione e gerencie as etapas de comunicação" separator>
        <x-slot:actions>
            <x-mary-button icon="o-arrow-uturn-left" @click="window.location.href = '{{ route('cadencias.index') }}'" />
            <x-mary-button icon="o-plus" label="Nova Etapa" class="btn-primary" wire:click="showModal" spinner="showModal" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-table :headers="$headers" :rows="$etapas" class="bg-base-100" with-pagination per-page="perPage" :per-page-values="[5, 10, 20]">
        @scope('actions', $row)
        <div class="flex space-x-2">
            <x-mary-button icon="o-trash" class="btn-sm btn-error" wire:click="delete({{ $row->id }})" title="Excluir" spinner="delete" />
            <x-mary-button icon="o-pencil" class="btn-sm btn-warning" wire:click="edit({{ $row->id }})" title="Editar" spinner="edit" />
        </div>
        @endscope
    </x-mary-table>

    {{-- Modal Padrão Mary UI --}}
    <x-mary-modal wire:model="etapaModal" box-class="max-w-2xl">
        <x-slot:header>
            <div class="flex justify-between items-center w-full">
                <h3 class="text-lg font-medium leading-6 text-gray-900">{{ $title }}</h3>
            </div>
        </x-slot:header>

        <x-mary-form wire:submit="save" class="space-y-6">
            <div class="flex justify-center">
                <x-mary-steps wire:model="step" steps-color="step-primary" classes="w-full max-w-md p-4 bg-base-200 rounded-lg mb-6">
                    <x-mary-step step="1" text="Básico" />
                    <x-mary-step step="2" text="Mensagem" />
                    <x-mary-step step="3" text="Agendamento" class="bg-warning/20" />
                </x-mary-steps>
            </div>

            {{-- Step 1: Básico --}}
            <div x-show="$wire.step === 1" class="space-y-4" wire:loading.remove wire:target="step">
                <x-mary-input label="Título da Etapa" wire:model.live="form.titulo" placeholder="Ex: Primeira Mensagem de Boas-vindas" required hint="Nome curto para identificar a etapa" />
                <x-mary-select label="Tipo de Envio" :options="$optionsSend" wire:model.live="form.type_send" hint="Escolha o canal de comunicação" required />
                <x-mary-toggle label="Envio Imediato?" wire:model.live="form.imediat" hint="Ative para enviar assim que a etapa for acionada, sem agendamento." />
                <x-mary-toggle label="Ativa?" wire:model.live="form.active" hint="Desative para pausar esta etapa." />

                <div class="flex justify-between pt-4 border-t">
                    <x-mary-button label="Cancelar" class="btn-outline" wire:click="closeModal" />
                    <x-mary-button label="Próximo" class="btn-primary" wire:click="next" spinner="next" />
                </div>
            </div>

            {{-- Step 2: Mensagem --}}
            <div x-show="$wire.step === 2" class="space-y-4" wire:loading.remove wire:target="step">
                {{-- <x-mary-textarea
                    label="Conteúdo da Mensagem"
                    wire:model.live="form.message_content"
                    placeholder="Digite a mensagem aqui... Use {nome} para personalizar com o nome do lead."
                    hint="Máx. 2000 caracteres. Suporta HTML simples para emails."
                    rows="6"
                    required /> --}}

                <x-mary-markdown wire:model="form.message_content" label="Conteúdo da Mensagem">
                    <x-slot:append>
                        <x-mary-button
                            icon="o-sparkles"
                            wire:click="generateSuggestion"
                            spinner
                            class="btn-ghost"
                            tooltip="Sugerir mensagem com AI"
                        />
                    </x-slot:append>
                </x-mary-markdown>

                <div class="flex justify-between pt-4 border-t">
                    <x-mary-button label="Anterior" class="btn-outline" wire:click="prev" />
                    <x-mary-button label="Próximo" class="btn-primary" wire:click="next" spinner="next" />
                </div>
            </div>

            {{-- Step 3: Agendamento --}}
            <div x-show="$wire.step === 3" class="space-y-4" wire:loading.remove wire:target="step">
                @if(!$form->imediat)
                <x-mary-alert title="Agendamento" description="Defina quando esta etapa deve ocorrer após a anterior." icon="o-clock" class="bg-info/10" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="space-y-2">
                        <x-mary-input label="Dias de Espera" wire:model.live="form.dias" type="number" placeholder="0" hint="Ex: 3 (envia após 3 dias da etapa anterior)" />
                    </div>
                    <div class="space-y-2">
                        <x-mary-input label="Hora Específica" wire:model.live="form.hora" type="time" placeholder="09:00" hint="Ex: 09:00 (dentro do range da cadência)" />
                    </div>
                </div>
                <div class="space-y-2">
                    <x-mary-datetime
                        label="Intervalo de Tempo"
                        hint="Define o intervalo em horas, minutos ou segundos após a etapa anterior (ex: 01:30:00 para 1 hora e 30 minutos). Pode ser usado independentemente de dias/hora para delays simples."
                        wire:model.live="form.intervalo"
                        icon="o-clock"
                        type="time"
                    />
                </div>
                @endif

                @if($form->imediat)
                    <x-mary-alert title="Envio Imediato" description="Esta etapa será enviada imediatamente ao acionar, respeitando o range da cadência. O intervalo pode ser usado para tentativas de reenvio se falhar." icon="o-bolt" class="bg-success/10" />
                @endif

                <div class="flex justify-between pt-4 border-t">
                    <x-mary-button label="Anterior" class="btn-outline" wire:click="prev" />
                    <x-mary-button label="Salvar Etapa" type="submit" class="btn-primary" spinner="save" />
                </div>
            </div>

            <div wire:loading wire:target="step,next,prev,save" class="flex justify-center items-center py-8">
                <span class="ml-2 text-gray-600">Processando...</span>
            </div>
        </x-mary-form>
    </x-mary-modal>
</div>