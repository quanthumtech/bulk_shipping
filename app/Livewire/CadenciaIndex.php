<?php

namespace App\Livewire;

use App\Livewire\Forms\CadenciaForm;
use App\Models\Cadencias;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class CadenciaIndex extends Component
{
    use WithPagination, Toast;

    public CadenciaForm $form;

    public bool $cadenciaModal = false;

    public bool $editMode = false;

    public $search = '';

    public int $perPage = 3;

    public $title = '';

    public function showModal()
    {
        $this->form->reset();
        $this->editMode = false;
        $this->cadenciaModal = true;
        $this->title = 'Nova Cadência';
    }

    public function edit($id)
    {
        $cadencias = Cadencias::find($id);

        if ($cadencias) {
            $this->form->setCadencias($cadencias);
            $this->editMode = true;
            $this->cadenciaModal = true;
        } else {
            $this->info('Cadência não encontrado.', position: 'toast-top');
        }
    }

    public function save()
    {
        try {
            if ($this->editMode) {
                $this->form->update();
                $this->editMode = false;
                $this->success('Cadência atualizado com sucesso!', position: 'toast-top', redirectTo: route('cadencias.index'));
            } else {
                $this->form->store();
                $this->success('Cadência cadastrado com sucesso!', position: 'toast-top', redirectTo: route('cadencias.index'));
            }

            $this->cadenciaModal = false;

        } catch (\Exception $e) {
            $this->error('Erro ao salvar o Cadência.', position: 'toast-top', redirectTo: route('cadencias.index'));

        }
    }

    public function delete($id)
    {
        Cadencias::find($id)->delete();
    }

    public function render()
    {
        $user = auth()->user();
        $cadencias_table = Cadencias::where('user_id', $user->id)
                    ->when($this->search, function($query) {
                        $query->where(function($q) {
                            $q->where('name', 'like', '%' . $this->search . '%')
                              ->orWhere('description', 'like', '%' . $this->search . '%');
                        });
                    })
                    ->paginate($this->perPage);

        $cadencias = Cadencias::where('user_id', $user->id)->where('active', 1)->get();

        foreach ($cadencias_table as $cadencia) {
            $cadencia->active = $this->getActiveUser($cadencia->active);
        }

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'description', 'label' => 'Descrição'],
            ['key' => 'active', 'label' => 'Ativo'],
            //['key' => 'actions', 'label' => 'Adicionar Etapas', 'class' => 'w-1 text-black'],
        ];

        $descriptionCard = 'Cadências são fluxos de comunicação que podem ser aplicados a um ou mais contatos. Cada cadência é composta por uma série de etapas,
                            que podem ser mensagens de texto, e-mails, ligações, entre outros. Clique no botão "+" para criar as etapas da sua cadência.';

        return view('livewire.cadencia-index', [
            'cadencias_table' => $cadencias_table,
            'cadencias' => $cadencias,
            'headers' => $headers,
            'descriptionCard' => $descriptionCard,
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
}
