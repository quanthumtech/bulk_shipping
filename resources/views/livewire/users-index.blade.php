<div>
    <x-mary-header title="Usuários" subtitle="User Settings" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Search..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button icon="o-funnel" />
            <x-mary-button label="API Versão" @click="$wire.showVersionModal()"/>
            <x-mary-button icon="o-plus" class="btn-primary" @click="window.location.href = '{{ route('users.config') }}'" />
        </x-slot:actions>
    </x-mary-header>
    {{-- INFO: table --}}
    <x-mary-table
        :headers="$headers"
        :rows="$users"
        striped
        class="bg-base-100"
        with-pagination per-page="perPage"
        :per-page-values="[3, 5, 10]"
    >

        {{-- Overrides `name` header --}}
        @scope('header_name', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `email` header --}}
        @scope('header_email', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `type_user` header --}}
        @scope('header_type_user_name', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `active` header --}}
        @scope('header_active_name', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `account chatwoot` header --}}
        @scope('header_chatwoot_accoumts', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `created_at` header --}}
        @scope('header_formatted_created_at', $header)
            <h3 class="text-xl font-bold text-base-content">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Special `actions` slot --}}
        @scope('actions', $users)
            <div class="flex space-x-2">
                <x-mary-button
                    icon="o-trash"
                    wire:click="delete({{ $users->id }})"
                    spinner
                    class="btn-sm btn-error"
                />
                <x-mary-button
                    icon="o-pencil-square"
                    @click="window.location.href = '{{ route('users.config', ['userId' => $users->id]) }}'"
                    class="btn-sm btn-primary"
                />
            </div>
        @endscope
    </x-mary-table>

    {{-- INFO: Slide users (NÃO ESTA SENDO USADO NO MOMENTO) --}}
    <x-mary-drawer
        wire:model="userModal"
        title="{{ $title }}"
        subtitle=""
        separator
        with-close-button
        close-on-escape
        class="w-11/13 lg:w-1/2"
        right
    >
        <x-mary-form wire:submit="save">

            {{-- INFO: campos --}}
            <div class="col-span-2 space-y-2">
                <x-mary-input label="Name" wire:model="form.name" placeholder="Digite aqui..." />
                <x-mary-input label="E-mail" wire:model="form.email" placeholder="Digite aqui..." />
                <x-mary-password label="Password" hint="It toggles visibility" wire:model="form.password" clearable />
            </div>

            <x-mary-hr />

            <div class="col-span-2 space-y-2">
                <x-mary-header title="Configurações" subtitle="Configurações para obter os contatos." size="text-2xl" />

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
            </div>

            <x-mary-hr />

            <div class="col-span-2 space-y-2">
                <x-mary-header title="Configurações" subtitle="Configurações da API de envio." size="text-2xl" />

                <x-mary-input
                    label="API key"
                    placeholder="Exemplo: adfxwj34...."
                    hint="Informe o Key da api de envio."
                    wire:model="form.apikey"
                />
                <x-mary-input
                    label="API Evolution"
                    hit="Informe a API de envio aqui"
                    placeholder="Exemplo: User Empresa criado no Evolution"
                    wire:model="form.api_post"
                />

            </div>

            <x-mary-hr />

            <div class="col-span-2 space-y-4">
                <x-mary-header title="Configurações" subtitle="Selecione o perfil e o status do usuário." size="text-2xl" />

                <x-mary-select label="Perfil do Usuário" :options="$options" wire:model="form.type_user" />
                <x-mary-toggle label="Usuário Ativo" wire:model="form.active" />
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.userModal = false" />
                <x-mary-button label="Create" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>

    {{-- INFO: Versões --}}
    <x-mary-modal wire:model="versionModal" title="API Versão" wire:ignore.self persistent>
        <x-mary-form wire:submit="saveVersion">

            {{-- INFO: campos --}}
            <x-mary-select label="Escolha a Versão" :options="$optionsVersion" wire:model="form_versions.version" />

            @if($form_versions->version)
                <div class="mt-2 text-sm text-gray-600">
                    Versão selecionada: {{ $form_versions->version }}
                </div>
            @endif

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.versionModal = false" />
                <x-mary-button label="Salvar" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>
