<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Versions extends Model
{
    protected $table = 'versions';

    protected $fillable = [
        'name',
        'type',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean'
    ];
}
