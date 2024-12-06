<div>
    <x-mary-header title="Usuários" subtitle="User Settings" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Search..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button icon="o-funnel" />
            <x-mary-button icon="o-plus" class="btn-primary" @click="$wire.showModal()" />
        </x-slot:actions>
    </x-mary-header>
    {{-- INFO: table --}}
    <x-mary-table
        :headers="$headers"
        :rows="$users"
        striped @row-click="$wire.edit($event.detail.id)"
        class="bg-white"
        with-pagination per-page="perPage"
        :per-page-values="[3, 5, 10]"
    >

        {{-- Overrides `name` header --}}
        @scope('header_name', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `email` header --}}
        @scope('header_email', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `type_user` header --}}
        @scope('header_type_user_name', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `active` header --}}
        @scope('header_active_name', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `account chatwoot` header --}}
        @scope('header_chatwoot_accoumts', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `created_at` header --}}
        @scope('header_formatted_created_at', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Special `actions` slot --}}
        @scope('actions', $users)
            <x-mary-button icon="o-trash" wire:click="delete({{ $users->id }})" spinner class="btn-sm btn-error" />
        @endscope
    </x-mary-table>

    {{-- INFO: Modal users --}}
    <x-mary-modal wire:model="userModal" class="backdrop-blur">
        <x-mary-form wire:submit="save">

            {{-- INFO: campos --}}
            <x-mary-input label="Name" wire:model="form.name" />
            <x-mary-input label="E-mail" wire:model="form.email" />
            <x-mary-password label="Password" hint="It toggles visibility" wire:model="form.password" clearable />

            <hr>

            <p>Configurações para obter os contatos.</p>

            <x-mary-input
                label="ID Conta Chatwoot"
                placeholder="Exemplo: 1"
                hint="Informe o token de acesso necessário para vincular sua conta ao Chatwoot. Apenas número por favor."
                wire:model="form.chatwoot_accoumts"
            />
            <x-mary-input
                label="Token"
                placeholder="Exemplo: adfxwj34...."
                wire:model="form.token_acess"
            />

            <hr>

            <p>Configurações da API de envio.</p>

            <x-mary-input
                label="API key"
                placeholder="Exemplo: adfxwj34...."
                hint="Informe o Key da api de envio."
                wire:model="form.apikey"
            />
            <x-mary-input
                label="API Evolution"
                hit="Informe a API de envio aqui"
                placeholder="Exemplo: https://api-exemplo"
                wire:model="form.api_post"
            />

            <x-mary-select label="Tipo de Usuário" :options="$options" wire:model="form.type_user" />
            <x-mary-toggle label="Ativo" wire:model="form.active" />




            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.userModal = false" />
                <x-mary-button label="Create" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>
