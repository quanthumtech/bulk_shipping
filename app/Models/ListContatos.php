<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListContatos extends Model
{
    protected $table = 'list_contacts';

    protected $fillable = [
        'contact_name',
        'phone_number',
        'chatwoot_id',
        'id_lead',
        'contact_email',
        'contact_number_empresa',
        'situacao_contato',
    ];

    public function lead()
    {
        return $this->belongsTo(SyncFlowLeads::class, 'id_lead');
    }
}