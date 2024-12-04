<?php

namespace App\Livewire;

use App\Livewire\Forms\PerfilForm;
use App\Models\User;
use Livewire\Component;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;
use Mary\Traits\WithMediaSync;

class PerfilIndex extends Component
{
    use Toast, WithFileUploads, WithMediaSync;

    public $user;

    public $name;

    public $email;

    public PerfilForm $form;

    public function mount($id = null)
    {
        $this->user = User::find($id);

        if ($this->user) {
            $this->name = $this->user->name;
            $this->email = $this->user->email;

            $this->form->setUsers($this->user);
        } else {
            $this->info('Usuário não encontrado.', position: 'toast-top');
        }
    }

    public function save()
    {
        try {
            $this->form->update();
            $this->success('Usuário atualizado com sucesso!', position: 'toast-top');
        } catch (\Exception $e) {
            $this->error('Erro ao salvar as alterações.', position: 'toast-top');

        }
    }

    public function render()
    {
        return view('livewire.perfil-index');
    }
}
