<?php

namespace App\Listeners;

use App\Events\GenericAudit;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Support\Facades\Auth;
use App\Models\System;

class LogGenericAudit
{
    public function handle(GenericAudit $event)
    {
        // Extract auditable data, fallback to System model if not provided
        $auditableType = $event->data['auditable_type'] ?? System::class;
        $auditableId = $event->data['auditable_id'] ?? 0;

        // Remove auditable_type and auditable_id from new_values to avoid duplication
        $newValues = array_diff_key($event->data, array_flip(['auditable_type', 'auditable_id']));

        Audit::create([
            'user_type' => Auth::user() ? get_class(Auth::user()) : null,
            'user_id' => Auth::id(),
            'event' => $event->event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => [],
            'new_values' => $newValues,
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'tags' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
