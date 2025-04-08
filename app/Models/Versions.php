<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Versions extends Model
{
    protected $table = 'versions';

    protected $fillable = [
        'name',
        'url_evolution',
        'type',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean'
    ];

    /**
     * Retorna a versão ativa da API.
     *
     * @return string|null
     */
    public static function getActiveVersion(): ?string
    {
        $activeVersion = self::where('active', true)->first();
        return $activeVersion ? $activeVersion->name : null;
    }
}
