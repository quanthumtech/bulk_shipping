<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OwenIt\Auditing\Models\Audit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Events\AuditCustom;
use Illuminate\Support\Facades\Event;

class CleanAudits extends Command
{
    protected $signature = 'audit:clean
                           {--days=365 : Número de dias para manter os registros}
                           {--limit=10000 : Número mínimo de registros a manter}
                           {--dry-run : Simula a limpeza sem excluir registros}';

    protected $description = 'Remove registros antigos da tabela audits para evitar crescimento excessivo';

    public function handle()
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        // Calcular a data limite para exclusão
        $cutoffDate = Carbon::now()->subDays($days);

        // Contar registros a serem excluídos
        $query = Audit::where('created_at', '<', $cutoffDate);
        $totalRecords = Audit::count();
        $recordsToDelete = $query->count();

        // Verificar se a exclusão deixará pelo menos o número mínimo de registros
        if ($totalRecords - $recordsToDelete < $limit) {
            $recordsToDelete = max(0, $totalRecords - $limit);
            $query = Audit::where('created_at', '<', $cutoffDate)
                ->orderBy('created_at', 'asc')
                ->limit($recordsToDelete);
        }

        // Logar/simular a operação
        $message = $dryRun
            ? "Simulação: {$recordsToDelete} registros de auditoria seriam excluídos (anteriores a {$cutoffDate})"
            : "Excluindo {$recordsToDelete} registros de auditoria anteriores a {$cutoffDate}";

        Event::dispatch(new AuditCustom(null, [
            'event' => 'audit.cleanup',
            'data' => [
                'message' => $message,
                'records_to_delete' => $recordsToDelete,
                'cutoff_date' => $cutoffDate->toDateTimeString(),
                'dry_run' => $dryRun,
            ],
        ]));

        $this->info($message);

        if (!$dryRun && $recordsToDelete > 0) {
            // Executar a exclusão em chunks para evitar sobrecarga
            $query->chunk(1000, function ($audits) {
                foreach ($audits as $audit) {
                    $audit->delete();
                }
            });

            $this->info("Exclusão concluída: {$recordsToDelete} registros removidos.");

            Event::dispatch(new AuditCustom(null, [
                'event' => 'audit.cleanup_completed',
                'data' => [
                    'message' => "Exclusão concluída: {$recordsToDelete} registros removidos.",
                    'records_deleted' => $recordsToDelete,
                    'cutoff_date' => $cutoffDate->toDateTimeString(),
                ],
            ]));
        } elseif ($recordsToDelete === 0) {
            $this->info("Nenhum registro encontrado para exclusão.");
        }
    }
}
