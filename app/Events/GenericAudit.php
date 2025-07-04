<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GenericAudit
{
    use Dispatchable, SerializesModels;

    public $event;
    public $data;

    public function __construct($event, array $data)
    {
        $this->event = $event;
        $this->data = $data;
    }
}
