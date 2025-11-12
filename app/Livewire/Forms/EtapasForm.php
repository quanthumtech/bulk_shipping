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

    public $intervalo;

    public $message_content;

    public $cadenciaId;

    public $imediat = false;

    public $active = true;

    public function rules()
    {
        $rules = [
            'titulo'          => 'required|string|max:255',
            'type_send'       => 'required|in:email,sms,whatsapp',
            'message_content' => 'required|string|max:2000',
            'imediat'         => 'boolean',
            'active'          => 'boolean',
            'dias'            => 'nullable|integer|min:0|max:30',
            'hora'            => 'nullable',
            'intervalo'       => 'nullable',
        ];

        return $rules;
    }

    public function setEtapas(Etapas $etapas)
    {
        $this->etapas = $etapas;
        $this->titulo = $etapas->titulo;
        $this->tempo = $etapas->tempo ?? '1';
        $this->unidade_tempo = $etapas->unidade_tempo ?? 'dias';
        $this->type_send = $etapas->type_send;
        $this->message_content = $etapas->message_content;
        $this->cadenciaId = $etapas->cadencia_id;
        $this->dias = $etapas->dias;
        $this->hora = $etapas->hora;
        $this->intervalo = $etapas->intervalo;
        $this->imediat = (bool) $etapas->imediat;
        $this->active = (bool) $etapas->active;
    }

    public function store()
    {
        // Se imediato, força null nos campos de agendamento
        if ($this->imediat) {
            $this->dias = null;
            $this->hora = null;
            $this->intervalo = null;
        }

        $this->validate();

        $data = [
            'titulo' => $this->titulo,
            'tempo' => $this->tempo,
            'unidade_tempo' => $this->unidade_tempo,
            'type_send' => $this->type_send,
            'message_content' => $this->message_content,
            'cadencia_id' => $this->cadenciaId,
            'dias' => $this->dias,
            'hora' => $this->hora,
            'intervalo' => $this->intervalo,
            'imediat' => $this->imediat,
            'active' => $this->active,
        ];

        Etapas::create($data);

        $this->reset();
    }

    public function update()
    {
        // Mesma lógica para update
        if ($this->imediat) {
            $this->dias = null;
            $this->hora = null;
            $this->intervalo = null;
        }

        $this->validate();

        $data = [
            'titulo' => $this->titulo,
            'tempo' => $this->tempo,
            'unidade_tempo' => $this->unidade_tempo,
            'type_send' => $this->type_send,
            'message_content' => $this->message_content,
            'cadencia_id' => $this->cadenciaId,
            'dias' => $this->dias,
            'hora' => $this->hora,
            'intervalo' => $this->intervalo,
            'imediat' => $this->imediat,
            'active' => $this->active,
        ];

        $this->etapas->update($data);

        $this->reset();
    }
}