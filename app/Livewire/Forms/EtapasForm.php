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

    public $tempo = '1';

    public $type_send = '';

    public $unidade_tempo = 'dias';

    public $dias;

    public $hora;

    public $message_content;

    public $cadenciaId;

    public $imediat;

    public $active;

    protected $rules = [
        'titulo' => 'required|string|max:255',
        //'tempo' => 'required|integer|min:1|max:30',
        //'unidade_tempo' => 'required|in:dias,horas,minutos',
        'type_send' => 'required|in:email,sms,whatsapp',
        'message_content' => 'required|string',
        'dias' => 'required|integer|min:0|max:30',
        'hora' => 'required',
    ];

    public function setEtapas(Etapas $etapas)
    {
        $this->etapas             = $etapas;
        $this->titulo             = $etapas->titulo;
        //$this->tempo              = $etapas->tempo;
        //$this->unidade_tempo      = $etapas->unidade_tempo;
        $this->type_send          = $etapas->type_send;
        $this->message_content    = $etapas->message_content;
        $this->cadenciaId         = $etapas->cadencia_id;
        $this->dias               = $etapas->dias;
        $this->hora               = $etapas->hora;
        $this->imediat            = (bool) $etapas->imediat;
        $this->active             = (bool) $etapas->active;

    }

    public function store()
    {
        $this->validate();

        $data = [
            'titulo'          => $this->titulo,
            'tempo'           => $this->tempo, //dias
            'unidade_tempo'   => $this->unidade_tempo, //horas
            'type_send'       => $this->type_send,
            'message_content' => $this->message_content,
            'cadencia_id'     => $this->cadenciaId,
            'dias'            => $this->dias,
            'hora'            => $this->hora,
            'imediat'         => $this->imediat,
            'active'          => $this->active,
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
            'dias'            => $this->dias,
            'hora'            => $this->hora,
            'imediat'         => $this->imediat,
            'active'          => $this->active,
        ];

        $this->etapas->update($data);

        $this->reset();
    }
}
