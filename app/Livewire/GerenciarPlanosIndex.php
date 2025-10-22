<?php

namespace App\Livewire;

use App\Livewire\Forms\PlanForm;
use App\Models\Plan;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class GerenciarPlanosIndex extends Component
{
    use WithPagination, Toast;

    public PlanForm $form;
    public bool $modal = false;
    public bool $editMode = false;
    public string $title = 'Criar Plano';
    public string $search = '';
    public int $perPage = 10;

    public $confirmingDelete = false;
    public $planToDelete = null;

    public function showModal(?int $id = null): void
    {
        $this->resetForm();
        $this->editMode = false;
        $this->title = 'Criar Plano';

        if ($id) {
            $plan = Plan::findOrFail($id);
            $this->form->setPlan($plan);
            $this->editMode = true;
            $this->title = 'Editar Plano';
        }

        $this->modal = true;
    }

    public function save(): void
    {
        try {
            if ($this->editMode) {
                $this->form->update();
                $this->success('Plano atualizado com sucesso!');
            } else {
                $this->form->create();
                $this->success('Plano criado com sucesso!');
            }

            $this->modal = false;
            $this->resetForm();
        } catch (\Exception $e) {
            $this->error('Erro ao salvar o plano: ' . $e->getMessage());
        }
    }

       public function confirmDelete($id)
    {
        $this->planToDelete = $id;
        $this->confirmingDelete = true;
    }

    public function cancelDelete()
    {
        $this->planToDelete = null;
        $this->confirmingDelete = false;
    }

    public function delete()
    {
        $plan = Plan::find($this->planToDelete);
        $plan->delete();
        
        $this->confirmingDelete = false;
        $this->planToDelete = null;
        
        $this->dispatch('notify', [
            'message' => 'Plano excluído com sucesso!',
            'type' => 'success'
        ]);
    }

    public function resetForm(): void
    {
        $this->form->reset();
    }

    public function render()
    {
        $query = Plan::query()
            ->where('name', 'like', '%' . $this->search . '%')
            ->orderBy('created_at', 'desc');

        $plans = $query->paginate($this->perPage);

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'price', 'label' => 'Preço (R$)'],
            ['key' => 'max_cadence_flows', 'label' => 'Fluxos Máx.'],
            ['key' => 'max_attendance_channels', 'label' => 'Canais Máx.'],
            ['key' => 'max_daily_leads', 'label' => 'Leads/Dia Máx.'],
            ['key' => 'message_storage_days', 'label' => 'IBM (dias)'],
            ['key' => 'support_level', 'label' => 'Suporte'],
            ['key' => 'active', 'label' => 'Ativo'],
            ['key' => 'actions', 'label' => 'Ações'],
        ];

        $supportOptions = [
            'basic' => 'Básico',
            'priority' => 'Prioritário',
            'dedicated' => 'Dedicado',
        ];

        $plans->through(function ($plan) use ($supportOptions) {
            $plan->formatted_price = $plan->price ? 'R$ ' . number_format($plan->price, 2, ',', '.') : 'Sob Demanda';
            $plan->support_name = $supportOptions[$plan->support_level] ?? $plan->support_level;
            $plan->active_name = $plan->active ? 'Sim' : 'Não';
            return $plan;
        });

        $billingOptions = [
            ['id' => 'monthly', 'name' => 'Mensal'],
            ['id' => 'yearly', 'name' => 'Anual'],
        ];

        return view('livewire.gerenciar-planos-index', compact('plans', 'headers', 'billingOptions'));
    }
}