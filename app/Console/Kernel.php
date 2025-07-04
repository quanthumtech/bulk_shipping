<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Defina os comandos do Artisan que podem ser registrados.
     *
     * @return void
     */
    protected function commands()
    {
        // Carregue todos os comandos personalizados do diretório Commands
        $this->load(__DIR__ . '/Commands');

        // Opcional: registre comandos adicionais via o arquivo de rotas console
        //require base_path('routes/console.php');
    }

    /**
     * Defina as tarefas que precisam ser agendadas.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('cadence:process')->everyMinute()->withoutOverlapping();

        // Executa a limpeza diariamente à 1h da manhã
        $schedule->command('audit:clean --days=365 --limit=10000')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/audit-clean.log'));
    }
}
