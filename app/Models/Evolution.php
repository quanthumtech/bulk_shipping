<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evolution extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'version_id', 'apikey', 'api_post', 'active'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function version()
    {
        return $this->belongsTo(Versions::class);
    }
}
