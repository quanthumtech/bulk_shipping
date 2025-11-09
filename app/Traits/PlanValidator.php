<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

trait PlanValidator
{
    use Toast;

    protected function validatePlanForAction(string $action, array $extraChecks = []): bool
    {
        $user = auth()->user();
        if (!$user->plan || !$user->plan->active) {
            $this->error('Nenhum plano ativo atribuído à sua conta. Contate o suporte.', position: 'toast-top');
            return false;
        }

        // Checagens baseadas na ação
        $checks = match ($action) {
            'create_cadence' => [$user->canCreateCadence()],
            'receive_lead'   => [$user->canReceiveDailyLead()],
            'create_group'   => [$user->plan->allowsAttendanceChannels(($user->used_attendance_channels ?? 0) + 1)],
            'send_mass'      => [$user->plan->allowsDailyLeads(($user->used_daily_sends ?? 0) + count($this->form->phone_number ?? []))],
            default          => [true, 'Ação não configurada para validação de plano.']
        };

        // Adicione checagens extras se fornecidas
        foreach ($extraChecks as $check) {
            if (!is_array($check) || !$check[0]) {
                $this->error(is_array($check) ? $check[1] ?? 'Limite do plano excedido.' : 'Erro na validação.', position: 'toast-top');
                return false;
            }
        }

        [$can, $msg] = $checks[0] ?? [false, 'Erro na checagem do plano.'];
        if (!$can) {
            $this->error($msg ?? 'Erro desconhecido', position: 'toast-top');
            return false;
        }

        // Incrementa contador se OK (ajuste o campo conforme a ação)
        match ($action) {
            'create_cadence' => $user->incrementCadenceCount(),
            'receive_lead'   => $user->incrementDailyLeadCount(),
            'create_group'   => $user->increment('used_attendance_channels'),
            'send_mass'      => $user->increment('used_daily_sends', count($this->form->phone_number ?? [])),
            default          => null
        };

        return true;
    }
}
