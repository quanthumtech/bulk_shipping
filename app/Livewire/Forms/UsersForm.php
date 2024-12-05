<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Livewire\WithFileUploads;
use Mary\Traits\WithMediaSync;
use Illuminate\Support\Collection;
use Mary\Traits\Toast;

class UsersForm extends Form
{
    use WithFileUploads, WithMediaSync, Toast;

    public ?User $users = null;

    #[validate('string', 'required')]
    public $name;

    #[validate('string', 'required')]
    public $email;

    #[validate('string', 'required')]
    public $password;

    public $chatwoot_accoumts;

    public $active;

    public $type_user;

    public $token_acess;

    public function setUsers(User $users)
    {
        $this->users             = $users;
        $this->name              = $users->name;
        $this->email             = $users->email;
        $this->chatwoot_accoumts = $users->chatwoot_accoumts;
        $this->active            = (bool) $users->active;
        $this->type_user         = $users->type_user;
        $this->token_acess       = $users->token_acess;
        $this->password          = '';
    }

    public function store()
    {

        $this->validate();

        User::create([
            'name'              => $this->name,
            'email'             => $this->email,
            'chatwoot_accoumts' => $this->chatwoot_accoumts,
            'active'            => $this->active,
            'type_user'         => $this->type_user,
            'token_acess'       => $this->token_acess,
            'password'          => Hash::make($this->password),
        ]);

        $this->reset();

    }

    public function update()
    {
        $this->validate();

        $data = [
            'name'              => $this->name,
            'email'             => $this->email,
            'chatwoot_accoumts' => $this->chatwoot_accoumts,
            'active'            => $this->active,
            'type_user'         => $this->type_user,
            'token_acess'       => $this->token_acess,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        $this->users->update($data);

        $this->reset();
    }
}
