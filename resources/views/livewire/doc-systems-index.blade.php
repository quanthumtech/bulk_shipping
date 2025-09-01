<div>
    <div class="flex h-screen">
        <div class="w-1/4 p-4 bg-gray-50 border-r border-gray-200 overflow-y-auto">
            <x-mary-header title="Páginas" subtitle="Arraste para reordenar" class="mb-4">
                <x-slot:actions>
                    @if ($isAdmin)
                        <x-mary-button label="Nova Página" icon="o-plus" class="btn-primary btn-sm" @click="$wire.showPageModal()" />
                    @endif
                </x-slot:actions>
            </x-mary-header>
            <x-mary-input placeholder="Pesquisar páginas..." wire:model.live.debounce.500ms="search" class="mb-4" />
            
            @if ($pages->isEmpty())
                <p class="text-center text-gray-500">Nenhuma página encontrada. Crie uma nova página para começar.</p>
            @else
                <ul id="sortable-pages" class="space-y-2">
                    @foreach ($pages as $page)
                        <li wire:key="page-{{ $page->id }}" data-id="{{ $page->id }}" class="{{ $isAdmin ? 'cursor-move' : '' }} p-2 bg-white border rounded flex justify-between items-center {{ $selectedPageId == $page->id ? 'bg-blue-100' : '' }}">
                            <div class="flex items-center space-x-2">
                                <div @click="$wire.selectPage({{ $page->id }})">
                                    <span class="font-medium">{{ $page->name }}</span>
                                    <p class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($page->created_at)->format('d/m/Y') }} • {{ $page->active ? 'Ativa' : 'Inativa' }}</p>
                                </div>
                            </div>
                            @if ($isAdmin)
                            <div class="flex space-x-2">
                                <x-mary-dropdown>
                                    <x-mary-menu-item title="Editar" icon="o-pencil" @click="$wire.editPage({{ $page->id }})"/>
                                    <x-mary-menu-item title="Excluir" icon="o-trash" @click="$wire.deletePage({{ $page->id }})"/>
                                    <x-mary-menu-item @click.stop="">
                                        <x-mary-toggle 
                                            label="Publicar" 
                                            value="{{ $page->active }}" 
                                            @click="$wire.toggleActive({{ $page->id }})" 
                                            right 
                                            class="toggle-primary"
                                        />
                                    </x-mary-menu-item>
                                </x-mary-dropdown>
                            </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="w-3/4 p-4 overflow-y-auto">
            <x-mary-header title="Documentações do Sistema" subtitle="{{ $selectedPage ? $selectedPage->name : 'Selecione uma página' }}" >
                <x-slot:actions>
                    <x-mary-button icon="o-question-mark-circle" @click="$wire.showHelpModal()" />
                </x-slot:actions>
            </x-mary-header>
            
            @if ($selectedPage)
                @if ($showEditor)
                    <div wire:ignore id="editorjs" class="border rounded p-4 mb-6 bg-white" data-content="{{ json_encode($selectedPage->content ?? ['blocks' => []]) }}"></div>
                @else
                    <div class="border rounded p-4 mb-6 bg-white">
                        {!! $conteudo !!}
                    </div>
                @endif
            @else
                <p class="text-center text-gray-500 mt-4">Selecione uma página para visualizar o conteúdo.</p>
            @endif
        </div>

        {{-- INFO: Modal de Página --}}
        @if ($isAdmin)
            <x-mary-modal wire:model="pageModal" title="{{ $title }}">
                <x-mary-form wire:submit.prevent="save">
                    <x-mary-input label="Nome da Página" wire:model="form.name" />
                    <x-mary-checkbox label="Ativa" wire:model="form.active" />
                    <x-mary-button type="submit" label="Salvar" class="btn-primary mt-4" />
                </x-mary-form>
            </x-mary-modal>
        @endif

        {{-- INFO: Modal de Ajuda --}}
        <x-mary-modal wire:model="helpModal" title="Ajuda">
            <div class="p-4">
                <p class="mb-2">Aqui estão algumas dicas para usar o sistema de documentação:</p>
                <ul class="list-disc list-inside">
                    @if ($isAdmin)
                        <li>Para criar uma nova página, clique no botão "Nova Página".</li>
                        <li>Para editar uma página existente, selecione-a na lista e clique em "Editar".</li>
                        <li>Use a área de conteúdo para adicionar texto, imagens e outros elementos à sua página.</li>
                        <li>Use o toggle no menu para publicar ou despublicar uma página.</li>
                    @else
                        <li>Selecione uma página na lista para visualizar seu conteúdo.</li>
                    @endif
                </ul>

                <x-mary-alert title="Observações" description="Consulte a documentação do Plugin Editor js para saber mais. Segue o link: https://editorjs.io/  Caso ao clicar na página o conteúdo não apareça, clique duas vezes." icon="o-exclamation-triangle" dismissible />
            </div>
        </x-mary-modal>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('livewire:init', () => {
            console.log('Livewire init event fired');
            @if ($isAdmin)
                Livewire.on('init-editor', ({ content }) => {
                    console.log('Received init-editor event in Blade:', content);
                    window.initializeEditor(content);
                });
            @endif
        });
    </script>
    @endpush

    @vite('resources/js/doc-systems.js')
</div>