<div>
    <x-mary-header title="Enviar mensagens" subtitle="Envio em massa" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Search..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button icon="o-plus" class="btn-primary" @click="$wire.showModal()" />
        </x-slot:actions>
    </x-mary-header>

    {{-- INFO: Aviso sobre como criar demandas --}}
    <x-mary-alert
        title="Dica: Como envar emnsagens em massa ou por cadência."
        icon="o-light-bulb"
        description="{!! $descriptionCard !!}"
        class="bg-yellow-50 text-yellow-900 border-yellow-200 mb-4"
        dismissible
    />

    {{-- INFO: modal slide --}}
    <x-mary-drawer
        wire:model="sendModal"
        title="{{ $title }}"
        subtitle=""
        separator
        with-close-button
        close-on-escape
        class="w-11/12 lg:w-1/3"
        right
    >
        <x-mary-form wire:submit="save">
            <x-mary-choices label="Contatos" wire:model="form.participants_id" :options="$contatos" allow-all />

            <x-mary-markdown wire:model="form.description" label="Mensagem" />

            <hr>

            <x-mary-file wire:model="form.file" multiple />

            <h3><strong>Configurar cadência</strong></h3>

            <hr>

            <div class="grid grid-cols-2 gap-4">
                <x-mary-datepicker
                    label="Prazo inicial"
                    wire:model="form.date_inicial"
                    icon="o-calendar"
                    :config="$configDatePicker"
                    hint="Prazo definido pelo responsável com base na análise realizada."
                />
                <x-mary-datepicker
                    label="Prazo final"
                    wire:model="form.date_final"
                    icon="o-calendar"
                    :config="$configDatePicker"
                    hint="Prazo definido pelo responsável com base na análise realizada."
                />
            </div>

            <x-mary-toggle label="Ativo" wire:model="form.active" />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.sendModal = false" />
                <x-mary-button label="Save" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>

</div>
