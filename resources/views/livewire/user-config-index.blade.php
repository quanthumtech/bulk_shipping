<div>
    <x-mary-header :title="$title" subtitle="Preencha os dados do usuário">
        <x-slot:middle class="!justify-end">
            <x-mary-button label="" icon="o-arrow-uturn-left"
                @click="window.location.href = '{{ route('users.index') }}'" />
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
                            <x-mary-header title="Dados do Usuário" subtitle="Preencha os dados para sua conta."
                                size="text-2xl" />
                        </div>
                        <div class="space-y-2">
                            <x-mary-input label="Name" wire:model="form.name" placeholder="Digite aqui..." />
                        </div>
                        <div class="space-y-2">
                            <x-mary-input label="E-mail" wire:model="form.email" placeholder="Digite aqui..." />
                        </div>
                        <div class="space-y-2">
                            <x-mary-password label="Password" hint="It toggles visibility" wire:model="form.password"
                                clearable />
                        </div>
                    </div>

                    {{-- Configurações do Usuário --}}
                    <div class="grid grid-cols-2 gap-4 mt-4 mb-8">
                        <div class="col-span-2 space-y-4">
                            <x-mary-header title="Configurações do Usuário"
                                subtitle="Selecione o perfil e o status do usuário." size="text-2xl" />
                        </div>
                        <div class="space-y-2">
                            <x-mary-select label="Perfil do Usuário" :options="$options" class="mb-4"
                                wire:model="form.type_user" />
                            <x-mary-toggle wire:model="form.active" class="self-start">
                                <x-slot:label>
                                    Usuário Ativo
                                </x-slot:label>
                            </x-mary-toggle>
                        </div>
                        <div class="space-y-2">
                            <x-mary-select label="Plano do Usuário" :options="$plans" class="mb-4"
                                wire:model="form.plan_id" />
                        </div>
                    </div>
                </x-mary-tab>

                <x-mary-tab name="chatwoot-tab">
                    <x-slot:label>
                        Configurações Chatwoot
                    </x-slot:label>
                    <div class="mt-4 space-y-2 mb-4">
                        <x-mary-input label="ID Conta Chatwoot" placeholder="Exemplo: 1"
                            hint="Informe o token de acesso necessário para vincular sua conta ao Chatwoot. Apenas número por favor."
                            wire:model="form.chatwoot_accoumts" />
                    </div>
                    <div class="space-y-2 mb-4">
                        <x-mary-input label="Token" placeholder="Exemplo: adfxwj34...."
                            wire:model="form.token_acess" />
                    </div>

                    {{-- Tabela de Agentes --}}
                    @if ($editMode && $userId)
                        <div class="col-span-2 space-y-4">
                            <x-mary-header title="Agentes" subtitle="Lista de agentes associados à conta Chatwoot."
                                size="text-2xl" />
                        </div>
                        <x-mary-table :headers="$headers" :rows="$agents" striped class="bg-base-100" with-pagination
                            per-page="perPage" :per-page-values="[3, 5, 10]">
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
                    <div class="mt-4 space-y-4 mb-4">
                        <x-mary-header title="Caixas Evolution"
                            subtitle="Adicione as caixas Evolution para este usuário." size="text-2xl" />

                        @foreach ($form->evolutions as $index => $evolution)
                            <div class="border p-4 rounded-lg space-y-4 relative">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <x-mary-select label="Versão" placeholder="Selecione a versão..."
                                            :options="$versions" hint="Informe a versão do Evolution." option-label="name"
                                            option-value="id"
                                            wire:model="form.evolutions.{{ $index }}.version_id" required />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-input label="API Key" placeholder="Exemplo: adfxwj34...."
                                            hint="Informe o Key da API de envio."
                                            wire:model="form.evolutions.{{ $index }}.apikey" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-input label="API Evolution"
                                            hint="Informe o identificador da API de envio"
                                            placeholder="Exemplo: User Empresa criado no Evolution"
                                            wire:model="form.evolutions.{{ $index }}.api_post" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-toggle wire:model="form.evolutions.{{ $index }}.active">
                                            <x-slot:label>
                                                Caixa Ativa
                                            </x-slot:label>
                                        </x-mary-toggle>
                                    </div>
                                </div>
                                @if (count($form->evolutions) > 1)
                                    <x-mary-button label="Remover Caixa" class="btn-error btn-sm absolute top-2 right-2"
                                        wire:click="removeEvolution({{ $index }})" icon="o-trash" />
                                @endif
                            </div>
                        @endforeach

                        <x-mary-button label="Adicionar Nova Caixa" class="btn-primary" wire:click="addEvolution"
                            icon="o-plus" />
                    </div>
                </x-mary-tab>

                <x-mary-tab name="integracao-tab">
                    <x-slot:label>
                        Integrações
                    </x-slot:label>
                    <div class="mt-4 space-y-4 mb-4">
                        <x-mary-header title="Integrações Zoho One"
                            subtitle="Adicione as integrações Zoho One para este usuário." size="text-2xl" />

                        @foreach ($form->zoho_integrations as $index => $zoho)
                            <div class="border p-4 rounded-lg space-y-4 relative">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <x-mary-input label="Zoho Client ID"
                                            placeholder="Exemplo: 1000.52CEX5NO0PL8FFZRD60P11GZK4E1NP"
                                            hint="Informe o Client ID do Zoho One."
                                            wire:model="form.zoho_integrations.{{ $index }}.client_id" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-input label="Zoho Client Secret"
                                            placeholder="Exemplo: 1e84265ee0e4d47ecae8eff48b32edbf24bfa86e0b"
                                            hint="Informe o Client Secret do Zoho One."
                                            wire:model="form.zoho_integrations.{{ $index }}.client_secret" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-input label="Zoho Authorization Code"
                                            placeholder="Exemplo: 1000.ea6b9ca02142a2d1877011941ac175ac..."
                                            hint="Cole o código retornado pela URL de autorização do Zoho."
                                            wire:model="form.zoho_integrations.{{ $index }}.code" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-input label="Zoho Refresh Token"
                                            placeholder="Exemplo: 1000.bd1c35d7c4002ff862213a178a1caeea..."
                                            hint="O refresh token será preenchido automaticamente após processar o código."
                                            wire:model="form.zoho_integrations.{{ $index }}.refresh_token"
                                            readonly />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-toggle wire:model="form.zoho_integrations.{{ $index }}.active">
                                            <x-slot:label>
                                                Integração Ativa
                                            </x-slot:label>
                                        </x-mary-toggle>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <x-mary-button label="Processar Código" class="btn-secondary btn-sm"
                                        wire:click="processZohoCode({{ $index }})" icon="o-check" />
                                </div>
                                @if (count($form->zoho_integrations) > 1)
                                    <x-mary-button label="Remover Integração"
                                        class="btn-error btn-sm absolute top-2 right-2"
                                        wire:click="removeZohoIntegration({{ $index }})" icon="o-trash" />
                                @endif
                            </div>
                        @endforeach

                        <x-mary-button label="Adicionar Nova Integração Zoho" class="btn-primary"
                            wire:click="addZohoIntegration" icon="o-plus" />
                    </div>
                </x-mary-tab>

                <x-mary-tab name="email-tab">
                    <x-slot:label>
                        SMTP / E-mail
                    </x-slot:label>
                    <div class="mt-4 space-y-4 mb-4">
                        <x-mary-header title="Integrações E-mail"
                            subtitle="Adicione as integrações de email para este usuário." size="text-2xl" />

                        @foreach ($form->email_integrations as $index => $email)
                            <div class="border p-4 rounded-lg space-y-4 relative">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <x-mary-input label="Host SMTP" placeholder="Exemplo: smtp.example.com"
                                            hint="Informe o host do servidor SMTP."
                                            wire:model="form.email_integrations.{{ $index }}.host" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-input label="Porta" placeholder="Exemplo: 587"
                                            hint="Informe a porta do servidor SMTP (ex: 587 para TLS)."
                                            wire:model="form.email_integrations.{{ $index }}.port" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-input label="Usuário" placeholder="Exemplo: user@example.com"
                                            hint="Informe o nome de usuário para autenticação SMTP."
                                            wire:model="form.email_integrations.{{ $index }}.username" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-password label="Senha" placeholder="Digite a senha..."
                                            hint="Informe a senha para autenticação SMTP."
                                            wire:model="form.email_integrations.{{ $index }}.password" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-select label="Criptografia" placeholder="Selecione a criptografia..."
                                            :options="[
                                                ['id' => 'none', 'name' => 'Nenhuma'],
                                                ['id' => 'tls', 'name' => 'TLS'],
                                                ['id' => 'ssl', 'name' => 'SSL'],
                                            ]" hint="Selecione o tipo de criptografia."
                                            option-label="name" option-value="id"
                                            wire:model="form.email_integrations.{{ $index }}.encryption" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-input label="E-mail de Origem"
                                            placeholder="Exemplo: noreply@example.com"
                                            hint="Informe o e-mail que aparecerá como remetente."
                                            wire:model="form.email_integrations.{{ $index }}.from_email" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-input label="Nome de Origem" placeholder="Exemplo: Minha Empresa"
                                            hint="Informe o nome que aparecerá como remetente."
                                            wire:model="form.email_integrations.{{ $index }}.from_name" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-mary-toggle
                                            wire:model="form.email_integrations.{{ $index }}.active">
                                            <x-slot:label>
                                                Integração Ativa
                                            </x-slot:label>
                                        </x-mary-toggle>
                                    </div>
                                </div>
                                @if (count($form->email_integrations) > 1)
                                    <x-mary-button label="Remover Integração"
                                        class="btn-error btn-sm absolute top-2 right-2"
                                        wire:click="removeEmailIntegration({{ $index }})" icon="o-trash" />
                                @endif
                            </div>
                        @endforeach

                        <x-mary-button label="Adicionar Nova Integração de E-mail" class="btn-primary"
                            wire:click="addEmailIntegration" icon="o-plus" />
                    </div>
                </x-mary-tab>
            </x-mary-tabs>

            <x-slot:actions>
                <x-mary-button label="Cancel" link="{{ route('users.index') }}" />
                <x-mary-button label="{{ $editMode ? 'Update' : 'Create' }}" class="btn-primary" type="submit"
                    spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </div>
</div>
