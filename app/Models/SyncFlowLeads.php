<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncFlowLeads extends Model
{
    protected $table = 'sync_flow_leads';

    protected $fillable = [
        'id_card',
        'contact_name',
        'contact_number',
        'contact_number_empresa',
        'contact_email',
        'estagio',
        'cadencia_id',
        'chatwoot_accoumts',
    ];
}
