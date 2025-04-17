<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatwootConversation extends Model
{
    protected $table = 'chatwoot_conversations';

    protected $fillable = [
        'sync_flow_lead_id',
        'conversation_id',
        'account_id',
        'agent_id',
        'status',
        'last_activity_at',
    ];

    public function lead()
    {
        return $this->belongsTo(SyncFlowLeads::class, 'sync_flow_lead_id');
    }
}
