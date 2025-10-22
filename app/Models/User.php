<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'photo',
        'chatwoot_accoumts',
        'active',
        'type_user',
        'token_acess',
        'apikey',
        'api_post',
        'plan_id',
        'plan_start_date',
        'plan_end_date',
        'used_cadence_flows',
        'used_daily_leads',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function evolutions()
    {
        return $this->hasMany(Evolution::class);
    }

    public function systemNotifications()
    {
        return $this->hasMany(SystemNotification::class);
    }

    public function zohoIntegrations()
    {
        return $this->hasMany(ZohoIntegration::class);
    }

    public function emailIntegrations()
    {
        return $this->hasMany(EmailIntegration::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function canCreateCadence(): bool
    {
        if (!$this->plan) {
            return false;
        }
        return $this->plan->allowsCadenceFlows($this->used_cadence_flows + 1);
    }

    public function canReceiveDailyLead(): bool
    {
        if (!$this->plan) {
            return false;
        }
       
        return $this->plan->allowsDailyLeads($this->used_daily_leads + 1);
    }

    public function incrementCadenceCount(): void
    {
        if ($this->plan && $this->canCreateCadence()) {
            $this->increment('used_cadence_flows');
        }
    }

    public function incrementDailyLeadCount(): void
    {
        if ($this->plan && $this->canReceiveDailyLead()) {
            $this->increment('used_daily_leads');
        }
    }
}
