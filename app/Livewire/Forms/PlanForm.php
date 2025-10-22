<?php

namespace App\Livewire\Forms;

use App\Models\Plan;
use Livewire\Form;

class PlanForm extends Form
{
    public ?int $id = null;
    public string $name = '';
    public ?float $price = null;
    public string $billing_cycle = 'monthly';
    public int $max_cadence_flows = 0;
    public int $max_attendance_channels = 0;
    public int $max_daily_leads = 0;
    public int $message_storage_days = 0;
    public string $support_level = 'basic';
    public bool $has_crm_integration = true;
    public bool $has_chatwoot_connection = true;
    public bool $has_scheduled_sending = true;
    public bool $has_operational_reports = true;
    public bool $has_performance_panel = true;
    public ?string $description = null;
    public bool $is_custom = false;
    public bool $active = true;

    public function setPlan(Plan $plan): void
    {
        $this->id = $plan->id;
        $this->name = $plan->name;
        $this->price = $plan->price;
        $this->billing_cycle = $plan->billing_cycle;
        $this->max_cadence_flows = $plan->max_cadence_flows;
        $this->max_attendance_channels = $plan->max_attendance_channels;
        $this->max_daily_leads = $plan->max_daily_leads;
        $this->message_storage_days = $plan->message_storage_days;
        $this->support_level = $plan->support_level;
        $this->has_crm_integration = $plan->has_crm_integration;
        $this->has_chatwoot_connection = $plan->has_chatwoot_connection;
        $this->has_scheduled_sending = $plan->has_scheduled_sending;
        $this->has_operational_reports = $plan->has_operational_reports;
        $this->has_performance_panel = $plan->has_performance_panel;
        $this->description = $plan->description;
        $this->is_custom = $plan->is_custom;
        $this->active = $plan->active;
    }

    public function create(): Plan
    {
        return Plan::create($this->all());
    }

    public function update(): void
    {
        if (!$this->id) {
            return;
        }

        $plan = Plan::findOrFail($this->id);
        $plan->update($this->all());
    }
}