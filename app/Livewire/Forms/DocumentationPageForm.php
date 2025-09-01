<?php

namespace App\Livewire\Forms;

use App\Models\DocumentationPage;
use Livewire\Attributes\Rule;
use Livewire\Form;

class DocumentationPageForm extends Form
{
    public ?DocumentationPage $page = null;

    #[Rule('required|string|max:255')]
    public string $name = '';

    #[Rule('boolean')]
    public bool $active = false;

    public function setPage(DocumentationPage $page): void
    {
        $this->page = $page;
        $this->name = $page->name;
        $this->active = (bool) $page->active;
    }

    public function store(): void
    {
        $data = $this->validate();
        DocumentationPage::create($data);
    }

    public function update(): void
    {
        $data = $this->validate();
        $this->page->update($data);
    }
}