<?php
namespace App\Livewire\Forms;

use App\Models\DocumentationPage;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Mary\Traits\Toast;

class DocumentationPageForm extends Form
{
    use Toast;

    public ?DocumentationPage $page = null;

    #[Validate('required|string|max:255')]
    public $name;

    public $active = true;

    public function setPage(DocumentationPage $page)
    {
        $this->page = $page;
        $this->name = $page->name;
        $this->active = $page->active;
    }

    public function store()
    {
        $this->validate();

        $maxPosition = DocumentationPage::max('position') ?? 0;

        DocumentationPage::create([
            'name' => $this->name,
            'active' => $this->active,
            'position' => $maxPosition + 1,
        ]);

        $this->reset();
    }

    public function update()
    {
        $this->validate();

        $this->page->update([
            'name' => $this->name,
            'active' => $this->active,
        ]);

        $this->reset();
    }
}