<?php

namespace App\Livewire;

use App\Livewire\Forms\IntegrationForm;
use App\Models\Versions;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class IntegrationsIndex extends Component
{
    use WithPagination, Toast;

    public IntegrationForm $form;

    public bool $modal = false;

    public bool $editMode = false;

    public $search = '';

    public int $perPage = 10;

    public $title = '';

    public function showModal()
    {
        $this->form->reset();
        $this->editMode = false;
        $this->modal = true;
        $this->title = 'Cadastrar Versão';
    }

    public function edit($id)
    {
        $version = Versions::find($id);

        if ($version) {
            $this->form->setVersion($version);
            $this->editMode = true;
            $this->modal = true;
            $this->title = 'Editar Versão';
        } else {
            $this->info('Versão não encontrada.', position: 'toast-top');
        }
    }

    public function save()
    {
        try {
            if ($this->editMode) {
                $this->form->update();
                $this->editMode = false;
                $this->success('Versão atualizada com sucesso!', position: 'toast-top', redirectTo: route('integrations.index'));
            } else {
                $this->form->store();
                $this->success('Versão cadastrada com sucesso!', position: 'toast-top', redirectTo: route('integrations.index'));
            }

            $this->modal = false;

        } catch (\Exception $e) {
            Log::info('Erro ao salvar a versão: ' . $e->getMessage());
            $this->error('Erro ao salvar a versão.', position: 'toast-top', redirectTo: route('integrations.index'));
        }
    }

    public function delete($id)
    {
        Versions::find($id)->delete();
        $this->success('Versão deletada com sucesso!', position: 'toast-top');
    }

    public function render()
    {
        $versions = Versions::where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('url_evolution', 'like', '%' . $this->search . '%')
                            ->paginate($this->perPage);

        foreach ($versions as $version) {
            $version->active_name = $version->active ? 'Ativa' : 'Inativa';
            $version->formatted_created_at = Carbon::parse($version->created_at)->format('d/m/Y');
        }

        return view('livewire.integrations-index', [
            'versions' => $versions,
        ]);
    }
}