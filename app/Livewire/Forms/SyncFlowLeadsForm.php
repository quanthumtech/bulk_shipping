<?php

namespace App\Livewire\Forms;

use App\Models\SyncFlowLeads;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Form;
use Mary\Traits\Toast;
use Mary\Traits\WithMediaSync;

class SyncFlowLeadsForm extends Form
{
    use WithFileUploads, WithMediaSync, Toast;

    public ?SyncFlowLeads $syncFlow = null;

    public $contact_name = '';

    public $contact_number = '';

    public $contact_number_empresa = '';

    public $contact_email = '';

    public $estagio = '';

    public $cadenciaId;

    protected $rules = [
        'contact_name'             => 'required|string',
        'contact_number'           => 'required|string',
        'contact_number_empresa'   => 'required|string',
        'contact_email'            => 'required|string',
        'estagio'                  => 'required|string',
    ];

    public function setSyncFlowLeads(SyncFlowLeads $syncFlow)
    {
        $this->syncFlow                 = $syncFlow;
        $this->contact_name             = $syncFlow->contact_name;
        $this->contact_number           = $syncFlow->contact_number;
        $this->contact_number_empresa   = $syncFlow->contact_number_empresa;
        $this->contact_email            = $syncFlow->contact_email;
        $this->estagio                  = $syncFlow->estagio;
        $this->cadenciaId               = $syncFlow->cadencia_id;

    }

    public function store()
    {
        $this->validate();

        $data = [
            'contact_name'             => $this->contact_name,
            'contact_number'           => $this->contact_number,
            'contact_number_empresa'   => $this->contact_number_empresa,
            'contact_email'            => $this->contact_email,
            'estagio'                  => $this->estagio,
        ];

       SyncFlowLeads::create($data);

        $this->reset();

    }

    public function update()
    {
        $this->validate();

        $data = [
            'contact_name'             => $this->contact_name,
            'contact_number'           => $this->contact_number,
            'contact_number_empresa'   => $this->contact_number_empresa,
            'contact_email'            => $this->contact_email,
            'estagio'                  => $this->estagio,
            'cadencia_id'              => $this->cadenciaId,
        ];

        $this->syncFlow->update($data);

        $this->reset();
    }
}
