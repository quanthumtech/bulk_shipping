<div class="flex h-screen">
    <div class="w-1/4 p-4 bg-gray-50 border-r border-gray-200 overflow-y-auto">
        <x-mary-header title="Páginas" subtitle="Arraste para reordenar" class="mb-4">
            <x-slot:actions>
                <x-mary-button label="Nova Página" icon="o-plus" class="btn-primary btn-sm" @click="$wire.showPageModal()" />
            </x-slot:actions>
        </x-mary-header>
        <x-mary-input placeholder="Pesquisar páginas..." wire:model.live.debounce.500ms="search" class="mb-4" />
        
        @if ($pages->isEmpty())
            <p class="text-center text-gray-500">Nenhuma página encontrada. Crie uma nova página para começar.</p>
        @else
            <ul id="sortable-pages" class="space-y-2">
                @foreach ($pages as $page)
                    <li wire:key="page-{{ $page->id }}" data-id="{{ $page->id }}" class="cursor-move p-2 bg-white border rounded flex justify-between items-center {{ $selectedPageId == $page->id ? 'bg-blue-100' : '' }}">
                        <div @click="$wire.selectPage({{ $page->id }})">
                            <span class="font-medium">{{ $page->name }}</span>
                            <p class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($page->created_at)->format('d/m/Y') }} • {{ $page->active ? 'Ativa' : 'Inativa' }}</p>
                        </div>
                        <div class="flex space-x-2">
                            <x-mary-button icon="o-pencil" class="btn-ghost btn-sm" @click="$wire.editPage({{ $page->id }})" />
                            <x-mary-button icon="o-trash" class="btn-ghost btn-sm text-red-500" @click="$wire.deletePage({{ $page->id }})" />
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="w-3/4 p-4 overflow-y-auto">
        <x-mary-header title="Documentações do Sistema" subtitle="{{ $selectedPage ? $selectedPage->name : 'Selecione uma página' }}" />
        
        @if ($selectedPage && $showEditor)
            <div wire:ignore id="editorjs" class="border rounded p-4 mb-6 bg-white" data-content="{{ json_encode($selectedPage->content ?? ['blocks' => []]) }}"></div>
            @script
            <script>
                console.log('Editor.js container rendered for page:', @json($selectedPage ? $selectedPage->name : 'none'));
            </script>
            @endscript
        @else
            <p class="text-center text-gray-500 mt-4">Selecione uma página para editar o conteúdo.</p>
        @endif
    </div>

    <x-mary-modal wire:model="pageModal" title="{{ $title }}">
        <form wire:submit.prevent="save">
            <x-mary-input label="Nome da Página" wire:model="form.name" />
            <x-mary-checkbox label="Ativa" wire:model="form.active" />
            <x-mary-button type="submit" label="Salvar" class="btn-primary mt-4" />
        </form>
    </x-mary-modal>
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:init', () => {
        console.log('Livewire init event fired');
        Livewire.on('init-editor', ({ content }) => {
            console.log('Received init-editor event in Blade:', content);
            window.initializeEditor(content);
        });
    });
</script>
@endpush

@vite('resources/js/doc-systems.js')