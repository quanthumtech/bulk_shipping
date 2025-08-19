<div>
    <x-mary-header title="Integrações Evolution" subtitle="Gerenciar integrações com o sistema Evolution" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-magnifying-glass" wire:model.live="search" placeholder="Buscar..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button icon="o-plus" class="btn-primary" @click="$wire.showModal()" label="Adicionar Versão" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Cards para versões --}}
    <div class="grid lg:grid-cols-3 gap-5">
        @foreach($versions as $version)
            <x-mary-card
                title="{{ $version->name }}"
                class="bg-base-100 shadow-lg"
                subtitle="{{ $version->url_evolution }}"
                subtitle-class="text-base-content/75"
                progress-indicator
            >
                <x-slot:menu>
                    <x-mary-badge value="{{ $version->active_name }}" class="{{ $version->active ? 'badge-success' : 'badge-error' }}" />
                </x-slot:menu>
                <x-slot:actions>
                    <x-mary-button icon="o-pencil-square" @click="$wire.edit({{ $version->id }})" class="btn-primary" />
                    <x-mary-button icon="o-trash" wire:click="delete({{ $version->id }})" class="btn-error" />
                </x-slot:actions>
            </x-mary-card>
        @endforeach
    </div>

    <div class="flex justify-center mt-4">
        {{ $versions->links() }}
    </div>

    {{-- Modal para cadastro/edição --}}
    <x-mary-modal wire:model="modal" title="{{ $title }}">
        <x-mary-form wire:submit="save">
            <x-mary-input label="Nome" wire:model="form.name" placeholder="Digite aqui..." required />
            <x-mary-input label="URL Evolution" wire:model="form.url_evolution" placeholder="https://.../message/sendText/" required />

            <x-mary-select 
                label="Versão API" 
                hint="Selecione a versão da API, consulte a documentação do Evolution para mais detalhes. link: https://doc.evolution-api.com"
                :options="$options" 
                wire:model="form.type" />

            <br>
            <x-mary-toggle label="Ativa" wire:model="form.active" />
            
            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.modal = false" />
                <x-mary-button label="Salvar" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>