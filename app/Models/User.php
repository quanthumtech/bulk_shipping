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
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function canCreateCadence(): array
    {
        if (!$this->plan) {
            return [false, 'Nenhum plano atribuído à sua conta.'];
        }
        if ($this->plan->max_cadence_flows > 0 && $this->used_cadence_flows >= $this->plan->max_cadence_flows) {
            return [false, "Limite de {$this->plan->max_cadence_flows} cadências atingido no plano '{$this->plan->name}'."];
        }
        return [true, 'OK'];
    }

    public function canReceiveDailyLead(): array
    {
        if (!$this->plan) {
            return [false, 'Nenhum plano atribuído à sua conta.'];
        }
        if ($this->plan->max_daily_leads > 0 && $this->used_daily_leads >= $this->plan->max_daily_leads) {
            return [false, "Limite de {$this->plan->max_daily_leads} leads/dia atingido no plano '{$this->plan->name}'."];
        }
        return [true, 'OK'];
    }

    public function incrementCadenceCount(): bool
    {
        [$can, $msg] = $this->canCreateCadence();
        if (!$can) {
            return false;
        }
        $this->increment('used_cadence_flows');
        return true;
    }

    public function incrementDailyLeadCount(): bool
    {
        [$can, $msg] = $this->canReceiveDailyLead();
        if (!$can) {
            return false;
        }
        $this->increment('used_daily_leads');
        return true;
    }
}
