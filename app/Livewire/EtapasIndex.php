<?php
namespace App\Livewire;

use App\Livewire\Forms\EtapasForm;
use App\Models\CadenceMessage;
use App\Models\Cadencias;
use App\Models\Etapas;
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

    public function mount($cadenciaId)
    {
        $this->form->cadenciaId = $cadenciaId;
    }

    public function showModal()
    {
        $this->form->reset();
        $this->etapaModal = true;
        $this->form->cadenciaId = $this->cadenciaId;
        $this->title = 'Adicionar Etapa';
    }

    public function save()
    {
        try {
            if ($this->editMode) {
                $this->form->update();
                $this->editMode = false;
                $this->success('Etapa atualizado com sucesso!', position: 'toast-top');
            } else {
                $this->form->store();
                $this->success('Etapa cadastrado com sucesso!', position: 'toast-top');
            }

            $this->etapaModal = false;

        } catch (\Exception $e) {
            dd($e);
            $this->error('Erro ao salvar o Etapa.', position: 'toast-top');

        }
    }

    public function edit($id)
    {
        $etapasEdit = Etapas::find($id);

        if ($etapasEdit) {
            $this->form->setEtapas($etapasEdit);
            $this->editMode = true;
            $this->etapaModal = true;
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

            // Busca a mensagem de cadence para a etapa atual
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
            //['key' => 'tempo', 'label' => 'Tempo'],
            ['key' => 'dias', 'label' => 'Dias'],
            ['key' => 'hora', 'label' => 'Hora'],
            ['key' => 'imediat_format', 'label' => 'Envio imediato'],
            ['key' => 'active_format', 'label' => 'Ativo'],
            ['key' => 'message_status', 'label' => 'Mensagem enviada'],
            ['key' => 'message_time', 'label' => 'Hora do envio'],
            //['key' => 'unidade_tempo', 'label' => 'Unidade de Tempo'],
        ];

        $options = [
            ['id' => '', 'name' => 'Selecione...'],
            ['id' => 'dias', 'name' => 'Dias'],
            ['id' => 'horas', 'name' => 'Horas'],
            ['id' => 'minutos', 'name' => 'Minutos'],
        ];

        $optionsSend = [
            ['id' => '', 'name' => 'Selecione...'],
            //['id' => 'email', 'name' => 'E-mail'], Em breve
            ['id' => 'whatsapp', 'name' => 'WhatsApp'],
        ];

        return view('livewire.etapas-index', [
            'cadencia' => $cadencia,
            'etapas' => $etapas,
            'headers' => $headers,
            'options' => $options,
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

}
