<?php

namespace App\Livewire;

use App\Livewire\Forms\UsersForm;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Mary\Traits\WithMediaSync;
use Illuminate\Support\Collection;
use Mary\Traits\Toast;
use Carbon\Carbon;

class UsersIndex extends Component
{
    use WithPagination, Toast;

    public UsersForm $form;

    public bool $userModal = false;

    public bool $editMode = false;

    public $search = '';

    public int $perPage = 3;

    public function showModal()
    {
        $this->form->reset();
        $this->editMode = false;
        $this->userModal = true;
    }

    public function edit($id)
    {
        $users = User::find($id);

        if ($users) {
            $this->form->setUsers($users);
            $this->editMode = true;
            $this->userModal = true;
        } else {
            $this->info('Usuário não encontrado.', position: 'toast-top');
        }
    }

    public function save()
    {
        try {
            if ($this->editMode) {
                $this->form->update();
                $this->editMode = false;
                $this->success('Usuário atualizado com sucesso!', position: 'toast-top', redirectTo: route('users.index'));
            } else {
                $this->form->store();
                $this->success('Usuário cadastrado com sucesso!', position: 'toast-top', redirectTo: route('users.index'));
            }

            $this->userModal = false;

        } catch (\Exception $e) {
            $this->error('Erro ao salvar o usuário.', position: 'toast-top', redirectTo: route('users.index'));

        }
    }

    public function delete($id)
    {
        User::find($id)->delete();
    }

    public function render()
    {
        $users = User::where('name', 'like', '%' . $this->search . '%')
                     ->orWhere('email', 'like', '%' . $this->search . '%')
                     ->paginate($this->perPage);

        foreach ($users as $user) {
            $user->type_user_name = $this->getUserTypeName($user->type_user);
            $user->formatted_created_at = Carbon::parse($user->created_at)->format('d/m/Y');
            $user->active_name = $this->getActiveUser($user->active);
        }

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'type_user_name', 'label' => 'Tipo usuário'],
            ['key' => 'active_name', 'label' => 'Ativo'],
            ['key' => 'chatwoot_accoumts', 'label' => 'id Chatwoot'],
            ['key' => 'formatted_created_at', 'label' => 'Criado']
        ];

        $options = [
            ['name' => 'Selecione...'],
            ['id' => 1, 'name' => 'SuperAdmin'],
            ['id' => 2, 'name' => 'Admin'],
            ['id' => 3, 'name' => 'User'],
        ];

        return view('livewire.users-index', [
            'users'   => $users,
            'headers' => $headers,
            'options' => $options
        ]);
    }

    public function getUserTypeName($typeId)
    {
        $types = [
            1 => 'SuperAdmin',
            2 => 'Admin',
            3 => 'User',
            4 => 'Developer',
        ];

        return $types[$typeId] ?? 'Desconhecido';
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
