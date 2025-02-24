<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Etapas extends Model
{
    protected $table = 'etapas';

    protected $fillable = ['cadencia_id', 'titulo', 'tempo', 'unidade_tempo', 'type_send', 'message_content', 'dias', 'hora', 'active', 'imediat'];

    public function cadencia()
    {
        return $this->belongsTo(Cadencias::class);
    }
}
