<?php

namespace App\Livewire\Forms;

use App\Models\GroupSend;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Livewire\WithFileUploads;
use Mary\Traits\WithMediaSync;

class GroupSendForm extends Form
{
    use WithFileUploads, WithMediaSync;

    public ?GroupSend $group = null;

    #[validate('string', 'required')]
    public $title;

    #[validate('string', 'required')]
    public $sub_title;

    #[validate('string', 'required')]
    public $description;

    #[validate('array')]
    public $phone_number = [];

    #[validate('array')]
    public $contact_name = [];

    #[validate('boolean', 'required')]
    public $active;

    public $image;

    public function setNote(GroupSend $group)
    {
        $this->group = $group;
        $this->title = $group->title;
        $this->sub_title = $group->sub_title;
        $this->description = $group->description;
        $this->active = (bool) $group->active;
        $this->phone_number = $group->phone_number ? json_decode($group->phone_number, true) : [];
        $this->contact_name = $group->contact_name ? json_decode($group->contact_name, true) : [];
        $this->image = $group->image ? asset('storage/' . $group->image) : null;
    }

    public function store()
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'sub_title' => $this->sub_title,
            'description' => $this->description,
            'phone_number' => json_encode($this->phone_number),
            'contact_name' => json_encode($this->contact_name),
            'active' => $this->active,
            'user_id' => auth()->id(),
        ];

        if ($this->image) {
            $imagePath = $this->image->store('groupeSend', 'public');
            $data['image'] = $imagePath;
        }

        GroupSend::create($data);
        $this->reset();
    }

    public function update()
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'sub_title' => $this->sub_title,
            'description' => $this->description,
            'phone_number' => json_encode($this->phone_number),
            'contact_name' => json_encode($this->contact_name),
            'active' => $this->active,
        ];

        if ($this->image && $this->image instanceof \Illuminate\Http\UploadedFile) {
            $data['image'] = $this->image->store('groupeSend', 'public');
        } else {
            $data['image'] = $this->group->image;
        }

        $this->group->update($data);
        $this->reset();
    }
}
