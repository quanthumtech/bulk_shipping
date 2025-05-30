<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cadencias extends Model
{
    protected $table = 'cadencias';

    protected $fillable = [
        'name',
        'description',
        'hora_inicio', //range de horario inicio
        'hora_fim', //range de horario fim
        'active',
        'user_id',
        'stage',
        'evolution_id',
    ];

    public function etapas()
    {
        return $this->hasMany(Etapas::class, 'cadencia_id');
    }

    public function evolution()
    {
        return $this->belongsTo(Evolution::class, 'evolution_id');
    }
}
