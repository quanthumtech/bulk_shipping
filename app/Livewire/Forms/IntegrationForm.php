<?php

namespace App\Livewire\Forms;

use App\Models\Versions;
use Livewire\Form;
use Livewire\Attributes\Validate;
use Mary\Traits\Toast;

class IntegrationForm extends Form
{
    use Toast;

    public ?Versions $version = null;

    #[Validate('required|string')]
    public $name = '';

    #[Validate('required|string|url')]
    public $url_evolution = '';

    #[Validate('nullable|string')]
    public $type = '';

    public $active = true;

    public function setVersion(Versions $version)
    {
        $this->version = $version;
        $this->name = $version->name;
        $this->url_evolution = $version->url_evolution;
        $this->type = $version->type;
        $this->active = (bool) $version->active;
    }

    public function store()
    {
        $this->validate();

        Versions::create([
            'name' => $this->name,
            'url_evolution' => $this->url_evolution,
            'type' => $this->type,
            'active' => $this->active,
        ]);

        $this->reset();
    }

    public function update()
    {
        $this->validate();

        $this->version->update([
            'name' => $this->name,
            'url_evolution' => $this->url_evolution,
            'type' => $this->type,
            'active' => $this->active,
        ]);

        $this->reset();
    }
}