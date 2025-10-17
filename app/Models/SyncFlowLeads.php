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
        'id_vendedor',
        'origem',
        'chatwoot_accoumts',
        'situacao_contato',
        'email_vendedor',
        'nome_vendedor',
        'chatwoot_status',
        'identifier',
        'contact_id',
        'completed_cadences',
    ];

    public function cadencia()
    {
        return $this->belongsTo(Cadencias::class, 'cadencia_id');
    }

    public function chatwootConversations()
    {
        return $this->hasMany(ChatwootConversation::class, 'sync_flow_lead_id');
    }

    public function hasCompletedCadence($cadencia_id)
    {
        $completed = is_array($this->completed_cadences)
            ? $this->completed_cadences
            : (empty($this->completed_cadences) ? [] : json_decode($this->completed_cadences, true));
        return !empty($completed) && in_array($cadencia_id, $completed);
    }

    public function markCadenceCompleted($cadencia_id)
    {
        $completed = is_array($this->completed_cadences)
            ? $this->completed_cadences
            : (empty($this->completed_cadences) ? [] : json_decode($this->completed_cadences, true));
        if (!in_array($cadencia_id, $completed)) {
            $completed[] = $cadencia_id;
            $this->completed_cadences = json_encode($completed);
            $this->save();
        }
    }
}
