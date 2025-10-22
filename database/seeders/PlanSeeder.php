<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::create([
            'name' => 'Essencial',
            'price' => 69.90,
            'max_cadence_flows' => 3,
            'max_attendance_channels' => 1,
            'max_daily_leads' => 20,
            'message_storage_days' => 30,
            'support_level' => 'basic',
            'description' => 'Ideal para pequenas operações ou quem está começando com automação.',
        ]);

        Plan::create([
            'name' => 'Avançado',
            'price' => 109.90,
            'max_cadence_flows' => 5,
            'max_attendance_channels' => 3,
            'max_daily_leads' => 50,
            'message_storage_days' => 90,
            'support_level' => 'priority',
            'description' => 'Para empresas com processos definidos que buscam escalar.',
        ]);

        Plan::create([
            'name' => 'Profissional',
            'price' => 189.90,
            'max_cadence_flows' => 10,
            'max_attendance_channels' => 5,
            'max_daily_leads' => 100,
            'message_storage_days' => 180,
            'support_level' => 'dedicated',
            'description' => 'Para operações que exigem alta performance e volume.',
        ]);

        Plan::create([
            'name' => 'Customizado',
            'price' => null,
            'max_cadence_flows' => 0,
            'max_attendance_channels' => 0,
            'max_daily_leads' => 0,
            'message_storage_days' => 0,
            'support_level' => 'priority',
            'is_custom' => true,
            'description' => 'Para grandes operações com demandas específicas.',
        ]);
    }
}
