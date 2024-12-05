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
        'message_content',
        'sent_at',
        'file',
        'active',
        'status',
        'group_id',
        'user_id'
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
}
