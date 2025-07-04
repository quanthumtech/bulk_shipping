<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ListContatos extends Model
{

    protected $table = 'list_contacts';

    protected $fillable = [
        'contact_name',
        'phone_number',
        'chatwoot_id',
    ];
}
