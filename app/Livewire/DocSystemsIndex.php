<?php

namespace App\Livewire;

use App\Enums\UserType;
use App\Livewire\Forms\DocumentationPageForm;
use App\Models\DocumentationPage;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\On;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Auth;
use HTMLPurifier;
use HTMLPurifier_Config;

class DocSystemsIndex extends Component
{
    use Toast;

    public DocumentationPageForm $form;

    public bool $pageModal = false;
    public bool $editMode = false;
    public bool $showEditor = false;

    public bool $helpModal = false;

    public string $search = '';
    public string $title = '';
    public ?int $selectedPageId = null;
    public $selectedPage = null;
    public $userType = null;

    public function mount()
    {
        $this->selectedPageId = null;
        $this->selectedPage = null;
        $this->showEditor = false;
        $this->userType = UserType::from(Auth::user()->type_user);
    }

    public function selectPage($pageId)
    {
        $this->selectedPageId = $pageId;
        $this->selectedPage = DocumentationPage::find($pageId);
        $this->showEditor = $this->userType === UserType::ADMIN;

        if ($this->selectedPage && $this->showEditor) {
            $content = $this->selectedPage->content ?? ['blocks' => []];
            $this->dispatch('init-editor', content: $content);
        }
    }

    public function showPageModal()
    {
        if ($this->userType !== UserType::ADMIN) {
            $this->error('Você não tem permissão para criar páginas.', position: 'toast-top');
            return;
        }

        $this->form->reset();
        $this->editMode = false;
        $this->pageModal = true;
        $this->title = 'Criar Página';
    }

    public function showHelpModal()
    {
        $this->helpModal = true;
    }

    public function editPage($id)
    {
        if ($this->userType !== UserType::ADMIN) {
            $this->error('Você não tem permissão para editar páginas.', position: 'toast-top');
            return;
        }

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
        if ($this->userType !== UserType::ADMIN) {
            $this->error('Você não tem permissão para salvar páginas.', position: 'toast-top');
            return;
        }

        try {
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
        }
    }

    #[On('saveContent')]
    public function saveContent($content)
    {
        if ($this->userType !== UserType::ADMIN) {
            $this->error('Você não tem permissão para salvar conteúdo.', position: 'toast-top');
            return;
        }

        try {
            if (!$this->selectedPageId) {
                $this->error('Nenhuma página selecionada para salvar o conteúdo.', position: 'toast-top');
                return;
            }

            $page = DocumentationPage::findOrFail($this->selectedPageId);

            $contentToSave = is_array($content) ? $content : ['blocks' => []];
            $page->update(['content' => $contentToSave]);

            $this->info('Conteúdo atualizado com sucesso!', position: 'toast-top');
        } catch (\Exception $e) {
            $this->error('Erro ao salvar o conteúdo: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function deletePage($id)
    {
        if ($this->userType !== UserType::ADMIN) {
            $this->error('Você não tem permissão para excluir páginas.', position: 'toast-top');
            return;
        }

        try {
            DocumentationPage::findOrFail($id)->delete();
            $this->success('Página excluída com sucesso!', position: 'toast-top');
            $this->selectedPageId = null;
            $this->selectedPage = null;
            $this->showEditor = false;
            $this->dispatch('init-editor', content: ['blocks' => []]);
        } catch (\Exception $e) {
            $this->error('Erro ao excluir a página.', position: 'toast-top');
        }
    }

    public function toggleActive($pageId)
    {
        if ($this->userType !== UserType::ADMIN) {
            $this->error('Você não tem permissão para alterar o status da página.', position: 'toast-top');
            return;
        }

        try {
            $page = DocumentationPage::findOrFail($pageId);
            $page->update(['active' => !$page->active]);
            $this->success('Status da página atualizado com sucesso!', position: 'toast-top');
           
            if ($this->selectedPageId === $pageId) {
                $this->selectedPage = $page->fresh();
            }
        } catch (\Exception $e) {
            $this->error('Erro ao atualizar o status da página: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function getContentHtml($content)
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'a[href|target],b,strong,i,em,br,span[class],pre,code');
        $purifier = new HTMLPurifier($config);

        if (!is_array($content) || !isset($content['blocks']) || !is_array($content['blocks']) || empty($content['blocks'])) {
            return '<p>Nenhum conteúdo disponível.</p>';
        }

        $html = '';
        foreach ($content['blocks'] as $index => $block) {
            if (!isset($block['type']) || !isset($block['data']) || !is_array($block['data'])) {
                $html .= '<p class="text-gray-500">Bloco inválido.</p>';
                continue;
            }

            switch ($block['type']) {
                case 'paragraph':
                    $text = isset($block['data']['text']) && is_string($block['data']['text']) ? $block['data']['text'] : '';
                    $html .= '<p class="mb-4">' . $purifier->purify($text) . '</p>';
                    break;

                case 'header':
                    $level = isset($block['data']['level']) && is_numeric($block['data']['level']) ? (int)$block['data']['level'] : 1;
                    $text = isset($block['data']['text']) && is_string($block['data']['text']) ? $block['data']['text'] : '';
                    $tag = 'h' . min(max($level, 1), 6);
                    $html .= "<{$tag} class=\"mb-4 font-bold\">" . $purifier->purify($text) . "</{$tag}>";
                    break;

                case 'list':
                    $style = isset($block['data']['style']) && is_string($block['data']['style']) ? $block['data']['style'] : 'unordered';
                    $items = isset($block['data']['items']) && is_array($block['data']['items']) ? $block['data']['items'] : [];

                    $tag = $style === 'ordered' ? 'ol' : 'ul';
                    $listClass = $tag === 'ol' ? 'list-decimal list-inside mb-4' : 'list-disc list-inside mb-4';
                    $html .= "<{$tag} class=\"{$listClass}\">";
                    foreach ($items as $itemIndex => $item) {
                        $itemText = '';
                        if (is_string($item)) {
                            $itemText = $item;
                        } elseif (is_array($item) && isset($item['content']) && is_string($item['content'])) {
                            $itemText = $item['content'];
                        } else {
                            $itemText = is_array($item) ? json_encode($item) : (string)$item;
                        }
                        $html .= '<li>' . $purifier->purify($itemText) . '</li>';
                    }
                    $html .= "</{$tag}>";
                    break;

                case 'simpleImage':
                    $url = isset($block['data']['url']) && is_string($block['data']['url']) ? $block['data']['url'] : '';
                    $caption = isset($block['data']['caption']) && is_string($block['data']['caption']) ? $block['data']['caption'] : '';
                    $stretched = isset($block['data']['stretched']) && $block['data']['stretched'] ? 'w-full' : 'max-w-full';
                    $withBorder = isset($block['data']['withBorder']) && $block['data']['withBorder'] ? 'border border-gray-300' : '';
                    $withBackground = isset($block['data']['withBackground']) && $block['data']['withBackground'] ? 'bg-gray-100 p-2' : '';
                    if ($url) {
                        $html .= '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '" class="mb-4 h-auto ' . $stretched . ' ' . $withBorder . ' ' . $withBackground . '" />';
                        if (!empty($caption)) {
                            $html .= '<p class="text-sm text-gray-500 mb-4">' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</p>';
                        }
                    }
                    break;

                case 'raw':
                    $rawHtml = isset($block['data']['html']) && is_string($block['data']['html']) ? $block['data']['html'] : '';
                    $html .= '<pre class="bg-gray-800 text-white p-4 rounded-lg mb-4 font-mono text-sm overflow-x-auto"><code>' . htmlspecialchars($rawHtml, ENT_QUOTES, 'UTF-8') . '</code></pre>';
                    break;

                default:
                    $html .= '<p class="text-gray-500">Bloco não suportado: ' . htmlspecialchars($block['type'], ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }
        return $html ?: '<p>Nenhum conteúdo disponível.</p>';
    }

    public function reorderPages($params)
    {
        if ($this->userType !== UserType::ADMIN) {
            $this->error('Você não tem permissão para reordenar páginas.', position: 'toast-top');
            return;
        }

        try {
            $pageIds = $params['pageIds'];
            foreach ($pageIds as $index => $pageId) {
                DocumentationPage::where('id', $pageId)->update(['position' => $index + 1]);
            }
            $this->success('Páginas reordenadas com sucesso!', position: 'toast-top');
        } catch (\Exception $e) {
            report($e);
            $this->error('Erro ao reordenar as páginas.', position: 'toast-top');
        }
    }

    public function render()
    {
        $query = DocumentationPage::where('name', 'like', '%' . $this->search . '%');
        
        if ($this->userType !== UserType::ADMIN) {
            $query->where('active', true);
        }
        $pages = $query->orderBy('position')->get();

        return view('livewire.doc-systems-index', [
            'pages' => $pages,
            'isAdmin' => $this->userType === UserType::ADMIN,
            'conteudo' => $this->selectedPage ? $this->getContentHtml($this->selectedPage->content ?? ['blocks' => []]) : '',
        ]);
    }
}