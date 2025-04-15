<div>
    <x-mary-header
        :title="$title"
        subtitle="Preencha os dados do usuário">
        <x-slot:middle class="!justify-end">
            <x-mary-button label="" icon="o-arrow-uturn-left" @click="window.location.href = '{{ route('users.index') }}'" />
        </x-slot:middle>
    </x-mary-header>

    <div class="max-w-4xl mx-auto mt-6">
        <x-mary-form wire:submit="save">

            {{-- Tabs para User, Chatwoot e API Evolution --}}
            <x-mary-tabs wire:model="myTab" active-tab="user-tab">
                <x-mary-tab name="user-tab">
                    <x-slot:label>
                        Configurações do Usuário
                    </x-slot:label>

                    {{-- Campos do Usuário --}}
                    <div class="grid grid-cols-2 gap-4 mb-8">
                        <div class="col-span-2 space-y-4">
                            <x-mary-header title="Dados do Usuário" subtitle="Preencha os dados para sua conta." size="text-2xl" />
                        </div>
                        <div class="space-y-2">
                            <x-mary-input label="Name" wire:model="form.name" placeholder="Digite aqui..." />
                        </div>
                        <div class="space-y-2">
                            <x-mary-input label="E-mail" wire:model="form.email" placeholder="Digite aqui..." />
                        </div>
                        <div class="space-y-2">
                            <x-mary-password label="Password" hint="It toggles visibility" wire:model="form.password" clearable />
                        </div>
                    </div>

                    {{-- Configurações do Usuário --}}
                    <div class="grid grid-cols-2 gap-4 mt-4 mb-8">
                        <div class="col-span-2 space-y-4">
                            <x-mary-header title="Configurações do Usuário" subtitle="Selecione o perfil e o status do usuário." size="text-2xl" />
                        </div>
                        <div class="space-y-2">
                            <x-mary-select label="Perfil do Usuário" :options="$options" class="mb-4" wire:model="form.type_user" />
                            <x-mary-toggle wire:model="form.active" class="self-start">
                                <x-slot:label>
                                    Usuário Ativo
                                </x-slot:label>
                            </x-mary-toggle>
                        </div>
                    </div>
                </x-mary-tab>

                <x-mary-tab name="chatwoot-tab">
                    <x-slot:label>
                        Configurações Chatwoot
                    </x-slot:label>
                    <div class="mt-4 space-y-2 mb-4">
                        <x-mary-input
                            label="ID Conta Chatwoot"
                            placeholder="Exemplo: 1"
                            hint="Informe o token de acesso necessário para vincular sua conta ao Chatwoot. Apenas número por favor."
                            wire:model="form.chatwoot_accoumts" />
                    </div>
                    <div class="space-y-2 mb-4">
                        <x-mary-input
                            label="Token"
                            placeholder="Exemplo: adfxwj34...."
                            wire:model="form.token_acess" />
                    </div>

                    {{-- Tabela de Agentes --}}
                    @if($editMode && $userId)
                        <div class="col-span-2 space-y-4">
                            <x-mary-header title="Agentes" subtitle="Lista de agentes associados à conta Chatwoot." size="text-2xl" />
                        </div>
                        <x-mary-table
                            :headers="$headers"
                            :rows="$agents"
                            striped
                            class="bg-base-100"
                            with-pagination
                            per-page="perPage"
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

                            {{-- Overrides `role` header --}}
                            @scope('header_role', $header)
                                <h3 class="text-xl font-bold text-base-content">
                                    {{ $header['label'] }}
                                </h3>
                            @endscope
                        </x-mary-table>
                    @else
                        <div class="mt-6 text-gray-500">
                            Os agentes serão buscados e exibidos aqui após o cadastro da conta Chatwoot.
                        </div>
                    @endif
                </x-mary-tab>

                <x-mary-tab name="api-tab">
                    <x-slot:label>
                        Configurações da API Evolution
                    </x-slot:label>
                    <div class="mt-4 space-y-2 mb-4">
                        <x-mary-input
                            label="API key"
                            placeholder="Exemplo: adfxwj34...."
                            hint="Informe o Key da api de envio."
                            wire:model="form.apikey" />
                    </div>
                    <div class="space-y-2 mb-4">
                        <x-mary-input
                            label="API Evolution"
                            hint="Informe a API de envio aqui"
                            placeholder="Exemplo: User Empresa criado no Evolution"
                            wire:model="form.api_post" />
                    </div>
                </x-mary-tab>
            </x-mary-tabs>

            <x-slot:actions>
                <x-mary-button label="Cancel" link="{{ route('users.index') }}" />
                <x-mary-button
                    label="{{ $editMode ? 'Update' : 'Create' }}"
                    class="btn-primary"
                    type="submit"
                    spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </div>
</div>
