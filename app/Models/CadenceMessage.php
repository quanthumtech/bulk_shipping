<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CadenceMessage extends Model
{
    protected $fillable = [
        'sync_flow_leads_id',
        'etapa_id',
        'enviado_em'
    ];

    public function syncFlowLead()
    {
        return $this->belongsTo(SyncFlowLeads::class, 'sync_flow_leads_id');
    }

    public function etapa()
    {
        return $this->belongsTo(Etapas::class, 'etapa_id');
    }
}
