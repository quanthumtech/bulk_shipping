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

    public $contato;

    public $file;

    public function setSend(Send $sends)
    {
        $this->sends        = $sends;
        $this->file         = $sends->file ? asset('send/' . $sends->file) : null;

    }

    public function store()
    {
        $this->validate();

        $data = [
            'contato'          => $this->contato,
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

    public function update()
    {
        $this->validate();

        $data = [
            'contato'          => $this->contato,
        ];

        if ($this->file && $this->file instanceof \Illuminate\Http\UploadedFile) {
            $data['file'] = $this->file->store('send', 'public');
        } else {
            $data['file'] = $this->sends->file;
        }

        $this->sends->update($data);

        $this->reset();
    }
}
