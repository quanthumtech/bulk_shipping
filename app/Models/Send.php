<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Send extends Model
{
    protected $table = 'menssages';

    protected $fillable = [
        'contact_name',
        'phone_number',
        'emails',
        'message_content',
        'sent_at',
        'file',
        'active',
        'status',
        'group_id',
        'user_id',
        'start_date',
        'end_date',
        'interval',
        'message_interval',
        'cadencias',
        'evolution_id',
    ];

    protected $casts = [
        'emails' => 'array',
    ];

    /**
     * Define a relação com o modelo User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(GroupSend::class);
    }

    public function evolution()
    {
        return $this->belongsTo(Evolution::class);
    }

    public function emailIntegration()
    {
        return $this->belongsTo(EmailIntegration::class);
    }
}
