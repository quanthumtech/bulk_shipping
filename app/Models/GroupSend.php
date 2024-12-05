<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupSend extends Model
{
    protected $table = 'group_send';

    protected $fillable = [
        'user_id',
        'send_id',
        'title',
        'sub_title',
        'description',
        'image',
        'phone_number',
        'active'
    ];
}
