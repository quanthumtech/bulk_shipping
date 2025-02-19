<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SyncFlowLeads;
use Carbon\Carbon;
use App\Services\ChatwootService;
use App\Models\CadenceMessage; // Opcional: para registrar os envios
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

        // Busque os leads que possuem cadência atribuída, com eager loading
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

            // Processa apenas a próxima etapa pendente
            if (isset($etapas[$currentEtapaIndex])) {
                $etapa = $etapas[$currentEtapaIndex];
                $dataAgendada = $lead->created_at
                    ->copy()
                    ->addDays((int)$etapa->dias)
                    ->setTimeFromTimeString($etapa->hora);

                if ($now->greaterThanOrEqualTo($dataAgendada) && !$this->etapaEnviada($lead, $etapa)) {
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
            }
        }
    }

    protected function etapaEnviada($lead, $etapa)
    {
        // Exemplo: verifique em uma tabela de envios se essa etapa já foi processada para o lead
        return CadenceMessage::where('sync_flow_leads_id', $lead->id)
                    ->where('etapa_id', $etapa->id)
                    ->exists();
    }

    protected function registrarEnvio($lead, $etapa)
    {
        // Registre o envio para não duplicar no futuro
        CadenceMessage::create([
            'sync_flow_leads_id' => $lead->id,
            'etapa_id' => $etapa->id,
            'enviado_em' => now(),
        ]);
    }
}
