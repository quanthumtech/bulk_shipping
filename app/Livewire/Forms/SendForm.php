<?php

namespace App\Livewire\Forms;

use App\Models\Send;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Livewire\WithFileUploads;
use Mary\Traits\WithMediaSync;

class SendForm extends Form
{
    use WithFileUploads, WithMediaSync;

    public ?Send $sends = null;

    protected $rules = [
        'phone_number' => 'required|array',
        'menssage_content' => 'required|string',
        'sent_at' => 'nullable|date',
        'active' => 'nullable|boolean',
        'status' => 'nullable|string',
        'file' => 'nullable|array',
        'file.*' => 'mimes:jpg,jpeg,png,pdf,docx|max:2048',
    ];

    public $contato, $file, $phone_number, $sent_at, $active, $status, $contact_name, $menssage_content, $group_id, $user_id;

    public function setSend(Send $sends)
    {
        $this->sends               = $sends;
        $this->contact_name        = $sends->contact_name;
        $this->phone_number        = $sends->phone_number ? json_decode($sends->phone_number, true) : [];
        $this->menssage_content    = $sends->menssage_content;
        $this->sent_at             = $sends->sent_at;
        $this->active              = (bool) $sends->active;
        $this->status              = $sends->status;
        $this->group_id            = $sends->group_id;
        $this->user_id             = $sends->user_id;
        $this->file                = $sends->file ? asset('send/' . $sends->file) : null;

    }

    public function store()
    {
        $this->validate();

        $data = [
            'contact_name'   => $this->contato,
            'phone_number'   => json_encode($this->phone_number),
            'message_content'=> $this->menssage_content,
            'sent_at'        => $this->sent_at,
            'active'         => $this->active,
            'status'         => $this->status,
            'user_id'        => auth()->id(),
            'group_id'       => $this->sends->group_id ?? $this->group_id,
        ];

        if ($this->file && is_array($this->file)) {
            $uploadedFiles = [];
            foreach ($this->file as $file) {
                $uploadedFiles[] = $file->store('send', 'public');
            }
            $data['file'] = json_encode($uploadedFiles);
        } elseif ($this->file) {
            $data['file'] = $this->file->store('send', 'public');
        }

        Send::create($data);

        $this->reset();
    }
}
