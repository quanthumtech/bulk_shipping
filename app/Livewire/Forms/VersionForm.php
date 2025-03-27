<?php

namespace App\Livewire\Forms;

use App\Models\Versions;
use Livewire\Attributes\Validate;
use Livewire\Form;

class VersionForm extends Form
{
    #[Validate('required')]
    public $version = '';

    public function store()
    {
        $this->validate();

        Versions::query()->update(['active' => false]);

        $version = Versions::where('id', $this->version)->first();
        if ($version) {
            $version->active = true;
            $version->save();
        } else {
            Versions::create([
                'name' => $this->version,
                'active' => true
            ]);
        }
    }
}
