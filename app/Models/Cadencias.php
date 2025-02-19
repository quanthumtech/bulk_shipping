<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cadencias extends Model
{
    protected $table = 'cadencias';

    protected $fillable = [
        'name',
        'description',
        'active',
        'user_id',
    ];

    public function etapas()
    {
        return $this->hasMany(Etapas::class, 'cadencia_id');
    }
}
