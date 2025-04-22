<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatwootsAgents extends Model
{
    use HasFactory;

    protected $table = 'chatwoot_agents';

    protected $fillable = [
        'user_id',
        'chatwoot_account_id',
        'agent_id',
        'name',
        'email',
        'role',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversations()
    {
        return $this->hasMany(ChatwootConversation::class, 'agent_id', 'chatwoot_agent_id');
    }
}
