<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SyncFlowLeads;
use Carbon\Carbon;
use App\Services\ChatwootService;
use App\Models\CadenceMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ProcessCadence extends Command
{
    protected $signature = 'cadence:process';
    protected $description = 'Processa os envios de mensagens de cadência conforme as etapas';

    public function handle()
    {
        $chatwootService = new ChatwootService();
        $now = Carbon::now();

        // Busca os leads com cadência atribuída
        $leads = SyncFlowLeads::whereNotNull('cadencia_id')
            ->with(['cadencia.etapas' => function($query) {
                $query->orderBy('id');
            }])
            ->get();

        foreach ($leads as $lead) {
            if (!$lead->cadencia) {
                Log::warning("Cadência não encontrada para o lead {$lead->id}");
                continue;
            }

            $etapas = $lead->cadencia->etapas;
            $lastSentEtapa = CadenceMessage::where('sync_flow_leads_id', $lead->id)
                ->orderBy('etapa_id', 'desc')
                ->first();

            $currentEtapaIndex = $lastSentEtapa ? $etapas->search(function($etapa) use ($lastSentEtapa) {
                return $etapa->id === $lastSentEtapa->etapa_id;
            }) + 1 : 0;

            // Processa a próxima etapa pendente
            if (isset($etapas[$currentEtapaIndex])) {
                $etapa = $etapas[$currentEtapaIndex];

                // Verifica se a etapa está ativa
                if (!$etapa->active) {
                    Log::info("Etapa {$etapa->id} do lead {$lead->id} não está ativa. Pulando...");
                    continue;
                }

                // Define o horário agendado para a etapa
                $dataAgendada = $lead->created_at
                    ->copy()
                    ->addDays((int)$etapa->dias)
                    ->setTimeFromTimeString($etapa->hora);

                // Verifica se já passou o horário agendado
                if ($now->greaterThanOrEqualTo($dataAgendada) && !$this->etapaEnviada($lead, $etapa)) {
                    // Converte os horários de início e fim da cadência para objetos Carbon
                    $horaInicio = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_inicio, $now->timezone);
                    $horaFim = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_fim, $now->timezone);

                    // Ajusta os horários ao dia atual
                    $horaInicio->setDate($now->year, $now->month, $now->day);
                    $horaFim->setDate($now->year, $now->month, $now->day);

                    // Verifica se o horário atual está dentro do range da cadência
                    if ($now->between($horaInicio, $horaFim)) {
                        $this->processarEtapa($lead, $etapa, $chatwootService);
                    } else {
                        // Se fora do horário, loga e pula para o próximo dia
                        Log::info("Etapa {$etapa->id} do lead {$lead->id} fora do horário permitido ({$lead->cadencia->hora_inicio} - {$lead->cadencia->hora_fim}). Adiada para o próximo dia.");
                    }
                }
            }
        }
    }

    protected function processarEtapa($lead, $etapa, $chatwootService)
    {
        $user = User::where('chatwoot_accoumts', $lead->chatwoot_accoumts)->first();

        if ($user && $user->api_post && $user->apikey) {
            Log::info("Processando etapa {$etapa->id} para lead {$lead->id}");

            try {
                $chatwootService->sendMessage(
                    $lead->contact_number,
                    $etapa->message_content,
                    $user->api_post,
                    $user->apikey
                );
                $this->registrarEnvio($lead, $etapa);
                $this->info("Mensagem da etapa {$etapa->id} enviada para o lead {$lead->id}");
            } catch (\Exception $e) {
                Log::error("Erro ao enviar mensagem para o lead {$lead->id}: " . $e->getMessage());
            }
        } else {
            Log::error("Usuário ou credenciais não encontradas para a conta Chatwoot: {$lead->chatwoot_accounts}");
        }
    }

    protected function etapaEnviada($lead, $etapa)
    {
        return CadenceMessage::where('sync_flow_leads_id', $lead->id)
                    ->where('etapa_id', $etapa->id)
                    ->exists();
    }

    protected function registrarEnvio($lead, $etapa)
    {
        CadenceMessage::create([
            'sync_flow_leads_id' => $lead->id,
            'etapa_id' => $etapa->id,
            'enviado_em' => now(),
        ]);
    }
}
