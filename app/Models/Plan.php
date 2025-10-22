<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'billing_cycle',
        'max_cadence_flows',
        'max_attendance_channels',
        'max_daily_leads',
        'message_storage_days',
        'support_level',
        'has_crm_integration',
        'has_chatwoot_connection',
        'has_scheduled_sending',
        'has_operational_reports',
        'has_performance_panel',
        'description',
        'is_custom',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'has_crm_integration' => 'boolean',
        'has_chatwoot_connection' => 'boolean',
        'has_scheduled_sending' => 'boolean',
        'has_operational_reports' => 'boolean',
        'has_performance_panel' => 'boolean',
        'is_custom' => 'boolean',
        'active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function allowsCadenceFlows(int $count): bool
    {
        return $this->max_cadence_flows === 0 || $count <= $this->max_cadence_flows;
    }

    public function allowsAttendanceChannels(int $count): bool
    {
        return $this->max_attendance_channels === 0 || $count <= $this->max_attendance_channels;
    }

    public function allowsDailyLeads(int $count): bool
    {
        return $this->max_daily_leads === 0 || $count <= $this->max_daily_leads;
    }
}