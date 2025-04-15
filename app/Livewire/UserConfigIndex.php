<?php

namespace App\Livewire;

use App\Livewire\Forms\UsersForm;
use App\Models\ChatwootsAgents;
use App\Models\User;
use App\Models\Versions;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class UserConfigIndex extends Component
{
    use Toast, WithPagination;

    public UsersForm $form;
    public bool $editMode = false;
    public ?int $userId = null;
    public string $title = 'Cadastrar Usuário';
    public string $myTab = 'user-tab';
    public int $perPage = 5;

    public function mount($userId = null)
    {
        $this->userId = $userId;

        if ($this->userId) {
            $user = User::find($this->userId);
            if ($user) {
                $this->form->setUsers($user);
                $this->editMode = true;
                $this->title = 'Editar Usuário';
            } else {
                $this->error('Usuário não encontrado.', position: 'toast-top');
                return redirect()->route('users.index');
            }
        }
    }

    public function save()
    {
        try {
            if ($this->editMode) {
                $this->form->update();
                $this->success('Usuário atualizado com sucesso!', position: 'toast-top');
            } else {
                $this->form->store();
                $this->success('Usuário cadastrado com sucesso!', position: 'toast-top');
            }

            return redirect()->route('users.index');
        } catch (\Exception $e) {
            $this->error('Erro ao salvar o usuário: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function addEvolution()
    {
        $this->form->addEvolution();
    }

    public function removeEvolution($index)
    {
        $this->form->removeEvolution($index);
    }

    public function getVersionsProperty()
    {
        return Versions::all()->map(function ($version) {
            return [
                'id' => $version->id,
                'name' => $version->name,
            ];
        })->toArray();
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'name', 'label' => 'Nome', 'class' => 'text-left'],
            ['key' => 'email', 'label' => 'Email', 'class' => 'text-left'],
            ['key' => 'role', 'label' => 'Papel', 'class' => 'text-left'],
        ];

        $options = [
            ['id' => '', 'name' => 'Selecione...'],
            ['id' => 1, 'name' => 'SuperAdmin'],
            ['id' => 2, 'name' => 'Admin'],
            ['id' => 3, 'name' => 'User'],
        ];

        $agents = $this->editMode && $this->userId
            ? ChatwootsAgents::where('user_id', $this->userId)
                ->paginate($this->perPage)
                ->through(function ($agent) {
                    return [
                        'id' => $agent->agent_id,
                        'name' => $agent->name,
                        'email' => $agent->email ?? 'N/A',
                        'role' => $agent->role,
                    ];
                })
            : [];

        return view('livewire.user-config-index', [
            'options' => $options,
            'headers' => $headers,
            'title' => $this->title,
            'agents' => $agents,
            'perPage' => $this->perPage,
            'versions' => $this->versions,
        ]);
    }
}
