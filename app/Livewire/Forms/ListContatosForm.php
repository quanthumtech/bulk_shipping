<?php

namespace App\Livewire\Forms;

use App\Models\ListContatos;
use App\Models\User;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Form;
use Mary\Traits\Toast;
use Mary\Traits\WithMediaSync;

class ListContatosForm extends Form
{
    use WithFileUploads, WithMediaSync, Toast;

    public ?ListContatos $contacts = null;

    public $contact_name;

    public $phone_number;

    public $rules = [
        'contact_name' => 'required|string|max:255',
        //'phone_number' => 'required|regex:/^\d{10,11}$/',  // Validação de número de telefone
    ];

    public function setContacts(ListContatos $contacts)
    {
        $this->contacts          = $contacts;
        $this->contact_name      = $contacts->contact_name;
        $this->phone_number      = $contacts->phone_number;
    }

    public function store()
    {

        $this->validate();

        $userChatwoot = User::find(auth()->id());

        // Sanitizando o número de telefone para garantir que só contenha números
        $this->phone_number = preg_replace('/\D/', '', $this->phone_number);

        // Adicionando o código do país "55" (Brasil) ao número
        if (strlen($this->phone_number) == 11) {
            $this->phone_number = '+55' . $this->phone_number;
        }

        ListContatos::create([
            'contact_name' => $this->contact_name,
            'phone_number' => $this->phone_number,
            'chatwoot_id'  => $userChatwoot->chatwoot_accoumts,
        ]);

        $this->reset();

    }
}
