<?php

namespace App\Livewire\Forms;

use App\Models\Etapas;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Form;
use Mary\Traits\Toast;
use Mary\Traits\WithMediaSync;

class EtapasForm extends Form
{
    use WithFileUploads, WithMediaSync, Toast;

    public ?Etapas $etapas = null;

    public $titulo = '';

    public $tempo = '';

    public $type_send = '';

    public $unidade_tempo = 'dias';

    public $message_content;

    public $cadenciaId;

    protected $rules = [
        'titulo' => 'required|string|max:255',
        'tempo' => 'required|integer|min:1|max:30',
        'unidade_tempo' => 'required|in:dias,horas,minutos',
    ];

    public function setEtapas(Etapas $etapas)
    {
        $this->etapas             = $etapas;
        $this->titulo             = $etapas->titulo;
        $this->tempo              = $etapas->tempo;
        $this->unidade_tempo      = $etapas->unidade_tempo;
        $this->type_send          = $etapas->type_send;
        $this->message_content    = $etapas->message_content;
        $this->cadenciaId         = $etapas->cadencia_id;
    }

    public function store()
    {
        $this->validate();

        $data = [
            'titulo'          => $this->titulo,
            'tempo'           => $this->tempo,
            'unidade_tempo'   => $this->unidade_tempo,
            'type_send'       => $this->type_send,
            'message_content' => $this->message_content,
            'cadencia_id'     => $this->cadenciaId,
        ];

       Etapas::create($data);

        $this->reset();

    }

    public function update()
    {
        $this->validate();

        $data = [
            'titulo'          => $this->titulo,
            'tempo'           => $this->tempo,
            'unidade_tempo'   => $this->unidade_tempo,
            'type_send'       => $this->type_send,
            'message_content' => $this->message_content,
            'cadencia_id'     => $this->cadenciaId,
        ];

        $this->etapas->update($data);

        $this->reset();
    }
}
