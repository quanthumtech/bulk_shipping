<?php

namespace App\Livewire\Forms;

use App\Models\Cadencias;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Form;
use Mary\Traits\Toast;
use Mary\Traits\WithMediaSync;

class CadenciaForm extends Form
{
    use WithFileUploads, WithMediaSync, Toast;

    public ?Cadencias $cadencias = null;

    public $name;

    public $hora_inicio;

    public $hora_fim;

    public $description;

    public $active;

    public $rules = [
        'name'        => 'required|string',
        'description' => 'nullable|string',
        'active'      => 'required|boolean',
    ];

    public function setCadencias(Cadencias $cadencias)
    {
        $this->cadencias             = $cadencias;
        $this->name                  = $cadencias->name;
        $this->hora_inicio            = $cadencias->hora_inicio;
        $this->hora_fim               = $cadencias->hora_fim;
        $this->description           = $cadencias->description;
        $this->active                = (bool) $cadencias->active;
    }

    public function store()
    {

        $this->validate();

        $data = [
            'name'        => $this->name,
            'hora_inicio' => $this->hora_inicio,
            'hora_fim'    => $this->hora_fim,
            'description' => $this->description,
            'active'      => $this->active,
            'user_id'     => auth()->id(),
        ];

       Cadencias::create($data);

        $this->reset();

    }

    public function update()
    {
        $this->validate();

        $data = [
            'name'        => $this->name,
            'hora_inicio' => $this->hora_inicio,
            'hora_fim'    => $this->hora_fim,
            'description' => $this->description,
            'active'      => $this->active,
        ];

        $this->cadencias->update($data);

        $this->reset();
    }
}
