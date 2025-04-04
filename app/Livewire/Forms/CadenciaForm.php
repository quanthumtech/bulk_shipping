<?php

namespace App\Livewire\Forms;

use App\Models\Cadencias;
use App\Models\SyncFlowLeads;
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

    public $stage = '';

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
        $this->stage                 = $cadencias->stage;
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
            'stage'      => $this->stage,
            'active'      => $this->active,
            'user_id'     => auth()->id(),
        ];

        $cadencias = Cadencias::create($data);

       /**
        * Aqui checa os leads do syncflow, se o estagio dele corresponde ao estagio da cadencia criada ele checa se
        * já oi atribuida a cadencia a esse lead se não ele atribui.
        */
        $sync_emp = SyncFlowLeads::whereRaw('UPPER(estagio) = ?', [strtoupper($this->stage)])->first();

        if ($sync_emp) {
            $sync_emp->cadencia_id = $cadencias->id;
            $sync_emp->save();
        }

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
            'stage'      => $this->stage,
            'active'      => $this->active,
        ];

        $this->cadencias->update($data);

        $this->reset();
    }
}
