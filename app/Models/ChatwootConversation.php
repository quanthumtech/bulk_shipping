<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatwootConversation extends Model
{
    protected $table = 'chatwoot_conversation';

    protected $fillable = [
        'sync_flow_lead_id',
        'conversation_id',
        'account_id',
        'agent_id',
        'status',
        'content',
        'last_activity_at',
    ];

    public function lead()
    {
        return $this->belongsTo(SyncFlowLeads::class, 'sync_flow_lead_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatwootMessage::class, 'chatwoot_conversation_id');
    }

    public function agent()
    {
        return $this->belongsTo(ChatwootsAgents::class, 'agent_id', 'agent_id'); // Ajustado para 'agent_id'
    }
}
