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

    public function setUsers(User $users)
    {
        $this->users      = $users;
        $this->name       = $users->name;
        $this->email      = $users->email;
        $this->password   = '';
    }

    public function store()
    {

        $this->validate();

        User::create([
            'name'      => $this->name,
            'email'     => $this->email,
            'password'  => Hash::make($this->password),
        ]);

        $this->reset();

    }

    public function update()
    {
        $this->validate();

        $data = [
            'name'      => $this->name,
            'email'     => $this->email,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        $this->users->update($data);

        $this->reset();
    }
}
