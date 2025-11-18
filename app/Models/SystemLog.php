<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
   protected $table = 'system_logs';

    protected $fillable = ['type', 'message', 'context', 'created_at', 'archived', 'archived_at'];

    public $timestamps = false;

    protected $casts = [
        'context' => 'array',
        'archived' => 'boolean',
    ];

    protected $dates = [
        'created_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /**
     * Get the user that owns the system log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);   
    }
}
