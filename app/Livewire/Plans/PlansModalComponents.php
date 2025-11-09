<?php

namespace App\Livewire\Plans;

use App\Models\Plan;
use Livewire\Component;
use Livewire\Attributes\On;

class PlansModalComponents extends Component
{
    public bool $isOpen = false;

    #[On('open-plans-modal')]
    public function openModal()
    {
        $this->isOpen = true;
        $this->dispatch('$refresh');
    }

    public function closeModal()
    {
        $this->isOpen = false;
        $this->dispatch('$refresh');
    }

    public function render()
    {
        $plans = Plan::where('active', true)->orderBy('name')->get()->map(function ($plan) {
            $price = $plan->price ? 'R$ ' . number_format($plan->price, 2, ',', '.') : 'Sob Demanda';
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $price,
                'max_cadence_flows' => $plan->max_cadence_flows == 0 ? 'Ilimitado' : $plan->max_cadence_flows,
                'max_daily_leads' => $plan->max_daily_leads == 0 ? 'Ilimitado' : $plan->max_daily_leads,
                'support_level' => ucfirst($plan->support_level),
            ];
        });

        $userPlan = auth()->user()->plan;

        return view('livewire.plans.plans-modal-components', [
            'plans' => $plans,
            'userPlan' => $userPlan
        ]);
    }
}