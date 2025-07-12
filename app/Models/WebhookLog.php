<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'webhook_type',
        'message',
        'context',
        'chatwoot_account_id',
        'archived',
    ];

    protected $casts = [
        'context' => 'array',
        'archived' => 'boolean',
    ];

    /**
     * Get the user that owns the webhook log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
