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
        'phone_number' => 'required|regex:/^\(\d{2}\)\s*\d{4,5}-\d{4}$/', // e.g., (00) 00000-0000 or (00) 0000-0000
    ];

    public function setContacts(ListContatos $contacts)
    {
        $this->contacts = $contacts;
        $this->contact_name = $contacts->contact_name;
        $this->phone_number = $contacts->phone_number;
    }

    public function store()
    {
        $this->validate();

        $userChatwoot = User::find(auth()->id());

        // Sanitize phone_number
        $phoneNumber = preg_replace('/\D/', '', $this->phone_number);

        // Add country code +55 if phone number is 10 or 11 digits
        if (strlen($phoneNumber) == 10 || strlen($phoneNumber) == 11) {
            $phoneNumber = '+55' . $phoneNumber;
        }

        // Create and return the ListContatos model
        $contact = ListContatos::create([
            'contact_name' => $this->contact_name,
            'phone_number' => $phoneNumber,
            'chatwoot_id' => $userChatwoot->chatwoot_accoumts,
        ]);

        $this->reset();

        return $contact;
    }
}
