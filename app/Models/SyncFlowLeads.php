<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncFlowLeads extends Model
{
    use \OwenIt\Auditing\Auditable;

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
        'situacao_contato',
        'email_vendedor',
        'nome_vendedor',
        'chatwoot_status',
    ];

    public function cadencia()
    {
        return $this->belongsTo(Cadencias::class, 'cadencia_id');
    }

    // Relação com ChatwootConversation
    public function chatwootConversations()
    {
        return $this->hasMany(ChatwootConversation::class, 'sync_flow_lead_id');
    }
}
