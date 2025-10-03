<?php

namespace App\Livewire\Forms;

use App\Models\ListContatos;
use App\Models\SyncFlowLeads;
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

    public $contact_email;

    public $contact_number_empresa;

    public $estagio = 'prospect';

    public $situacao_contato = 'ativo';

    public $create_as_lead = false;

    public $rules = [
        'contact_name' => 'required|string|max:255',
        'phone_number' => 'required|string|max:20',
        'contact_email' => 'nullable|email|max:255',
        'contact_number_empresa' => 'nullable|string|max:20',
        'estagio' => 'nullable|string|max:100',
        'situacao_contato' => 'nullable|string|max:50',
        'create_as_lead' => 'boolean',
    ];

    public function setContacts(ListContatos $contacts)
    {
        $this->contacts          = $contacts;
        $this->contact_name      = $contacts->contact_name;
        $this->phone_number      = $contacts->phone_number;
        $this->contact_email     = $contacts->contact_email ?? '';
        $this->contact_number_empresa = $contacts->contact_number_empresa ?? '';
        $this->estagio           = $contacts->estagio ?? 'prospect';
        $this->situacao_contato  = $contacts->situacao_contato ?? 'ativo';
        $this->create_as_lead    = !empty($contacts->id_lead);
    }

    public function store()
    {
        $this->validate();

        $userChatwoot = User::find(auth()->id());

        $this->phone_number = preg_replace('/\D/', '', $this->phone_number);

        if (strlen($this->phone_number) == 11) {
            $this->phone_number = '+55' . $this->phone_number;
        }

        if (!empty($this->contact_number_empresa)) {
            $this->contact_number_empresa = preg_replace('/\D/', '', $this->contact_number_empresa);
            if (strlen($this->contact_number_empresa) == 11) {
                $this->contact_number_empresa = '+55' . $this->contact_number_empresa;
            }
        }

        $contatoData = [
            'contact_name' => $this->contact_name,
            'phone_number' => $this->phone_number,
            'chatwoot_id'  => $userChatwoot->chatwoot_accoumts,
            'id_lead'      => null,
            'contact_email' => $this->contact_email,
            'contact_number_empresa' => $this->contact_number_empresa ?? null,
            'estagio' => $this->estagio ?? null,
            'situacao_contato' => $this->situacao_contato ?? null,
        ];

        $listContato = ListContatos::create($contatoData);

        if ($this->create_as_lead) {

            logger()->info('Criando lead para o contato: ' . $this->contact_name);

            $this->validate([
                'contact_email' => 'required|email|max:255',
                'estagio' => 'required|string|max:100',
                'situacao_contato' => 'required|string|max:50',
            ]);

            $lead = SyncFlowLeads::create([
                'contact_name' => $this->contact_name,
                'contact_number' => $this->phone_number,
                'contact_number_empresa' => $this->contact_number_empresa ?? null,
                'contact_email' => $this->contact_email,
                'estagio' => $this->estagio,
                'situacao_contato' => $this->situacao_contato,
                'chatwoot_accoumts' => $userChatwoot->chatwoot_accoumts,
                'id_card' => null,
                'cadencia_id' => null,
                'email_vendedor' => null,
                'nome_vendedor' => null,
                'chatwoot_status' => 'pending',
                'identifier' => null,
                'contact_id' => null,
                'completed_cadences' => null,
            ]);

            $listContato->update(['id_lead' => $lead->id]);
        }

        $this->reset();
    }
}