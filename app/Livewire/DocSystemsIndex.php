<?php

namespace App\Livewire;

use App\Livewire\Forms\DocumentationPageForm;
use App\Models\DocumentationPage;
use Livewire\Component;
use Livewire\Attributes\On;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Log;

class DocSystemsIndex extends Component
{
    use Toast;

    public DocumentationPageForm $form;

    public bool $pageModal = false;
    public bool $editMode = false;
    public bool $showEditor = false;

    public string $search = '';
    public string $title = '';
    public ?int $selectedPageId = null;
    public $selectedPage = null;

    public function mount()
    {
        $firstPage = DocumentationPage::orderBy('position')->first();
        if ($firstPage) {
            $this->selectedPageId = $firstPage->id;
            $this->selectedPage = $firstPage;
        }
    }

    public function selectPage($pageId)
    {
        $this->selectedPageId = $pageId;
        $this->selectedPage = DocumentationPage::find($pageId);
        $this->showEditor = true;
        Log::info('Selected page:', ['page_id' => $pageId, 'page' => $this->selectedPage?->toArray()]);

        if ($this->selectedPage) {
            $content = $this->selectedPage->content ?? ['blocks' => []];
            Log::info('Dispatching init-editor with content:', ['content' => $content]);
            $this->dispatch('init-editor', content: $content);
        } else {
            Log::info('No page found, dispatching empty init-editor');
            $this->dispatch('init-editor', content: ['blocks' => []]);
        }
    }

    public function showPageModal()
    {
        $this->form->reset();
        $this->editMode = false;
        $this->pageModal = true;
        $this->title = 'Criar Página';
    }

    public function editPage($id)
    {
        $page = DocumentationPage::find($id);

        if ($page) {
            $this->form->setPage($page);
            $this->editMode = true;
            $this->pageModal = true;
            $this->title = 'Editar Página';
        } else {
            $this->error('Página não encontrada.', position: 'toast-top');
        }
    }

    public function save()
    {
        try {
            Log::info('Saving page form:', [
                'name' => $this->form->name,
                'active' => $this->form->active,
            ]);

            if ($this->editMode) {
                $this->form->update();
                $this->success('Página atualizada com sucesso!', position: 'toast-top');
            } else {
                $this->form->store();
                $this->success('Página criada com sucesso!', position: 'toast-top');
            }

            $this->pageModal = false;
            if ($this->editMode && $this->selectedPageId) {
                $this->selectPage($this->selectedPageId);
            }
        } catch (\Exception $e) {
            $this->error('Erro ao salvar a página: ' . $e->getMessage(), position: 'toast-top');
            Log::error('Error saving page: ' . $e->getMessage());
        }
    }

    #[On('saveContent')]
    public function saveContent($content)
    {
        try {
            Log::info('saveContent method called with input:', [
                'page_id' => $this->selectedPageId,
                'content' => $content,
                'content_type' => gettype($content),
            ]);

            if (!$this->selectedPageId) {
                Log::warning('No page selected to save content.');
                $this->error('Nenhuma página selecionada para salvar o conteúdo.', position: 'toast-top');
                return;
            }

            $page = DocumentationPage::findOrFail($this->selectedPageId);
            Log::info('Found page for saving content:', [
                'page_id' => $this->selectedPageId,
                'page' => $page->toArray(),
            ]);

            $contentToSave = is_array($content) ? $content : ['blocks' => []];
            $page->update(['content' => $contentToSave]);

            Log::info('Content saved successfully for page:', [
                'page_id' => $this->selectedPageId,
                'content' => $contentToSave,
            ]);
            $this->success('Conteúdo salvo com sucesso!', position: 'toast-top');
        } catch (\Exception $e) {
            Log::error('Error saving content:', [
                'page_id' => $this->selectedPageId,
                'content' => $content,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Erro ao salvar o conteúdo: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function deletePage($id)
    {
        try {
            DocumentationPage::findOrFail($id)->delete();
            $this->success('Página excluída com sucesso!', position: 'toast-top');
            $this->selectedPageId = null;
            $this->selectedPage = null;
            $this->showEditor = false;
            $this->dispatch('init-editor', content: ['blocks' => []]);
        } catch (\Exception $e) {
            $this->error('Erro ao excluir a página.', position: 'toast-top');
            Log::error('Error deleting page: ' . $e->getMessage());
        }
    }

    public function reorderPages($params)
    {
        try {
            $pageIds = $params['pageIds'];
            foreach ($pageIds as $index => $pageId) {
                DocumentationPage::where('id', $pageId)->update(['position' => $index + 1]);
            }
            $this->success('Páginas reordenadas com sucesso!', position: 'toast-top');
        } catch (\Exception $e) {
            $this->error('Erro ao reordenar as páginas.', position: 'toast-top');
            Log::error('Error reordering pages: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $pages = DocumentationPage::where('name', 'like', '%' . $this->search . '%')
            ->orderBy('position')
            ->get();

        Log::info('Pages data:', ['pages' => $pages->toArray()]);

        return view('livewire.doc-systems-index', [
            'pages' => $pages,
        ]);
    }
}