<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Livewire\WithFileUploads;
use Mary\Traits\WithMediaSync;

class PerfilForm extends Form
{
    use WithFileUploads, WithMediaSync;

    public ?User $users = null;

    #[validate('string', 'required')]
    public $name;

    #[validate('string', 'required')]
    public $email;

    public $photo;

    public function setUsers(User $users)
    {
        $this->users      = $users;
        $this->name       = $users->name;
        $this->email      = $users->email;
        $this->photo      = $users->photo ? asset('storage/' . $users->photo) : null;
    }

    public function update()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($this->photo && $this->photo instanceof \Illuminate\Http\UploadedFile) {
            $data['photo'] = $this->photo->store('photos', 'public');
        }

        $this->users->update($data);

    }
}
