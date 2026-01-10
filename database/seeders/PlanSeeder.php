<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'price' => 189.90,
                'max_cadence_flows' => 3,
                'max_attendance_channels' => 1,
                'max_daily_leads' => 20,
                'message_storage_days' => 30,
                'support_level' => 'basic',
                'active' => true,
                'description' => 'Ideal para quem quer iniciar automações sem complexidade — custo-benefício com estrutura corporativa.',
            ],
            [
                'name' => 'Professional',
                'price' => 499.90,
                'max_cadence_flows' => 5,
                'max_attendance_channels' => 3,
                'max_daily_leads' => 50,
                'message_storage_days' => 90,
                'support_level' => 'priority',
                'active' => true,
                'description' => 'Foco em empresas que já possuem fluxo ativo de atendimento digital. Entrega robustez e insights.',
            ],
            [
                'name' => 'Business',
                'price' => 999.90,
                'max_cadence_flows' => 10,
                'max_attendance_channels' => 5,
                'max_daily_leads' => 100,
                'message_storage_days' => 180,
                'support_level' => 'dedicated',
                'active' => true,
                'description' => 'Voltado a operações estruturadas, múltiplos atendentes e análise de performance — nível corporativo.',
            ],
            [
                'name' => 'Enterprise',
                'price' => null,
                'max_cadence_flows' => 0,
                'max_attendance_channels' => 0,
                'max_daily_leads' => 0,
                'message_storage_days' => 0,
                'support_level' => 'priority',
                'is_custom' => true,
                'active' => true,
                'description' => 'Solução customizada para corporações e ecossistemas — integra operações de grande escala e suporte sob demanda.',
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['name' => $planData['name']],
                $planData
            );
        }
    }
}