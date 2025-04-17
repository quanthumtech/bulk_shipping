<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatwootMessage extends Model
{
    protected $fillable = [
        'chatwoot_conversation_id',
        'content',
        'message_id',
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatwootConversation::class, 'chatwoot_conversation_id');
    }
}
