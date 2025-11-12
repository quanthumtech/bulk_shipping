<?php

namespace App\Livewire;

use App\Livewire\Forms\EtapasForm;
use App\Models\CadenceMessage;
use App\Models\Cadencias;
use App\Models\Etapas;
use Illuminate\Container\Attributes\Log;
use Illuminate\Support\Facades\Log as FacadesLog;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class EtapasIndex extends Component
{
    use WithPagination, Toast;

    public EtapasForm $form;

    public $cadenciaId;

    public $title = '';

    public bool $etapaModal = false;
    public bool $editMode = false;

    public int $step = 1;

    public function mount($cadenciaId)
    {
        $this->form->cadenciaId = $cadenciaId;
    }

    public function showModal()
    {
        $this->form->reset();
        $this->etapaModal = true;
        $this->form->cadenciaId = $this->cadenciaId;
        $this->form->imediat = false;
        $this->form->active = true;
        $this->step = 1;
        $this->title = 'Adicionar Etapa';
        $this->editMode = false;
    }

    public function closeModal()
    {
        $this->etapaModal = false;
        $this->step = 1;
        $this->form->reset();
    }

    public function next()
    {
        if ($this->step === 1) {
            $this->validate([
                'form.titulo' => 'required|string|max:255',
                'form.type_send' => 'required|in:email,sms,whatsapp',
            ]);
        } elseif ($this->step === 2) {
            $this->validate([
                'form.message_content' => 'required|string|max:2000',
            ]);
        }

        if ($this->step < 3) {
            $this->step++;
        }
    }

    public function prev()
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function save()
    {
        if ($this->step !== 3) {
            $this->error('Complete todos os passos antes de salvar.', position: 'toast-top');
            return;
        }

        try {
            $checaRange = true;
            if (!$this->form->imediat && $this->form->hora) {
                $checaRange = $this->isHoraDentroDaCadencia($this->form->cadenciaId, $this->form->hora);
            }
            if (!$checaRange) {
                $this->error('A hora informada não está dentro do intervalo da cadência.', position: 'toast-top');
                return;
            }

            if ($this->editMode) {
                $this->form->update();
                $this->editMode = false;
                $this->success('Etapa atualizada com sucesso!', position: 'toast-top');
            } else {
                $this->form->store();
                $this->success('Etapa cadastrada com sucesso!', position: 'toast-top');
            }

            $this->etapaModal = false;
            $this->step = 1;
        } catch (\Exception $e) {
            $this->error('Erro ao salvar a etapa: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function edit($id)
    {
        $etapasEdit = Etapas::find($id);
        if ($etapasEdit) {
            $this->form->setEtapas($etapasEdit);
            $this->editMode = true;
            $this->etapaModal = true;
            $this->title = 'Editar Etapa';
            $this->step = 1;
        } else {
            $this->info('Etapa não encontrada.', position: 'toast-top');
        }
    }

    public function delete($id)
    {
        Etapas::findOrFail($id)->delete();
        $this->success('Etapa deletada com sucesso!', position: 'toast-top');
    }

    public function render()
    {
        $cadencia = Cadencias::findOrFail($this->cadenciaId);
        $etapas = Etapas::where('cadencia_id', $this->cadenciaId)->paginate(5);

        foreach ($etapas as $etapa) {
            $etapa->active_format = $this->getActiveUser($etapa->active);
            $etapa->imediat_format = $this->getImediat($etapa->imediat);

            $cadenceMessage = CadenceMessage::where('etapa_id', $etapa->id)->first();
            if ($cadenceMessage) {
                $etapa->message_status = 'Enviada';
                $etapa->message_time = $cadenceMessage->enviado_em;
            } else {
                $etapa->message_status = 'Não enviada';
                $etapa->message_time = null;
            }
        }

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'titulo', 'label' => 'Título'],
            ['key' => 'dias', 'label' => 'Dias'],
            ['key' => 'hora', 'label' => 'Hora'],
            ['key' => 'intervalo', 'label' => 'Intervalo'],
            ['key' => 'imediat_format', 'label' => 'Envio imediato'],
            ['key' => 'active_format', 'label' => 'Ativo'],
            ['key' => 'message_status', 'label' => 'Mensagem enviada'],
            ['key' => 'message_time', 'label' => 'Hora do envio'],
        ];

        $options = [
            ['id' => '', 'name' => 'Selecione...'],
            ['id' => 'dias', 'name' => 'Dias'],
            ['id' => 'horas', 'name' => 'Horas'],
            ['id' => 'minutos', 'name' => 'Minutos'],
        ];

        $optionsSend = [
            ['id' => '', 'name' => 'Selecione...'],
            ['id' => 'whatsapp', 'name' => 'WhatsApp'],
        ];

        return view('livewire.etapas-index', [
            'cadencia'    => $cadencia,
            'etapas'      => $etapas,
            'headers'     => $headers,
            'options'     => $options,
            'optionsSend' => $optionsSend,
        ]);
    }

    public function getActiveUser($active)
    {
        $type = [
            1 => 'Ativo',
            0 => 'Inativo'
        ];

        return $type[$active] ?? '';
    }

    public function getImediat($imediat)
    {
        $type = [
            1 => 'Sim',
            0 => 'Não',
            null => 'Não'
        ];

        return $type[$imediat] ?? '';
    }

    private function isHoraDentroDaCadencia($cadenciaId, $hora)
    {
        $cadencia = Cadencias::find($cadenciaId);
        if (!$cadencia) return false;

        $horaInformada = \Carbon\Carbon::createFromFormat('H:i', substr($hora, 0, 5));
        $horarioInicio = \Carbon\Carbon::createFromFormat('H:i:s', $cadencia->hora_inicio);
        $horarioFim    = \Carbon\Carbon::createFromFormat('H:i:s', $cadencia->hora_fim);

        return $horaInformada->between($horarioInicio, $horarioFim);
    }
}
