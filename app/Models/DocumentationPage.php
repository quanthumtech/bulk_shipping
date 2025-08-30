<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentationPage extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'active', 'content', 'position'];

    protected $casts = [
        'content' => 'array',
        'active' => 'boolean',
    ];
}
