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
    protected $chatwootServices;

    public function handle()
    {
        while (true) {
            $now = Carbon::now();
            $this->info("Horário atual no início do ciclo: " . $now);

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

                /**
                 * vale apenas para stage lead novo.
                 *
                 */
                if ($lead->situacao_contato === 'Contato Efetivo') {
                    Log::info("Lead {$lead->id} possui situação 'Contato Efetivo'. Pulando execução das etapas.");
                    continue;
                }

                if (!$this->isValidTime($lead->cadencia->hora_inicio) || !$this->isValidTime($lead->cadencia->hora_fim)) {
                    Log::warning("Horário inválido ou ausente para a cadência do lead {$lead->id}. Pulando...");
                    continue;
                }

                $etapas = $lead->cadencia->etapas;
                $lastSentEtapa = CadenceMessage::where('sync_flow_leads_id', $lead->id)
                    ->orderBy('etapa_id', 'desc')
                    ->first();

                $currentEtapaIndex = $lastSentEtapa ? $etapas->search(function($etapa) use ($lastSentEtapa) {
                    return $etapa->id === $lastSentEtapa->etapa_id;
                }) + 1 : 0;

                if (isset($etapas[$currentEtapaIndex])) {
                    $etapa = $etapas[$currentEtapaIndex];

                    if (!$etapa->active) {
                        Log::info("Etapa {$etapa->id} do lead {$lead->id} não está ativa. Pulando...");
                        continue;
                    }

                    $dataAgendada = $lead->created_at
                        ->copy()
                        ->addDays((int)$etapa->dias)
                        ->setTimeFromTimeString($etapa->hora);

                    Log::debug("Horário agendado para a etapa {$etapa->id} do lead {$lead->id}: " . $dataAgendada);
                    $this->info("Horário agendado para a etapa {$etapa->id} do lead {$lead->id}: " . $dataAgendada);

                    if ($now->greaterThanOrEqualTo($dataAgendada) && !$this->etapaEnviada($lead, $etapa)) {
                        Log::debug("Horário atual: " . $now . " é maior ou igual a " . $dataAgendada);
                        $this->info("Horário atual: " . $now . " é maior ou igual a " . $dataAgendada);

                        $horaInicio = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_inicio, $now->timezone);
                        $horaFim = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_fim, $now->timezone);

                        $horaInicio->setDate($now->year, $now->month, $now->day);
                        $horaFim->setDate($now->year, $now->month, $now->day);

                        $this->info("Intervalo permitido: {$horaInicio} até {$horaFim}");

                        if ($now->between($horaInicio, $horaFim)) {
                            $this->info("Dentro do horário permitido. Processando etapa...");
                            $this->processarEtapa($lead, $etapa);
                        } else {
                            Log::info("Etapa {$etapa->id} do lead {$lead->id} fora do horário permitido ({$lead->cadencia->hora_inicio} - {$lead->cadencia->hora_fim}). Adiada para o próximo dia.");
                        }
                    } else {
                        $this->info("Etapa {$etapa->id} do lead {$lead->id} ainda não está pronta ou já foi enviada.");
                    }
                }
            }

            $this->info("Ciclo concluído. Aguardando 60 segundos para o próximo ciclo.");
            sleep(6);
        }
    }

    /**
     * Verifica se o horário é válido no formato H:i:s
     */
    protected function isValidTime($time)
    {
        if (is_null($time) || trim($time) === '') {
            return false;
        }

        try {
            Carbon::createFromFormat('H:i:s', $time);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function processarEtapa($lead, $etapa)
    {

        $this->chatwootServices = new ChatwootService();
        $user = User::where('chatwoot_accoumts', $lead->chatwoot_accoumts)->first();

        if ($user && $user->api_post && $user->apikey) {
            Log::info("Processando etapa {$etapa->id} para lead {$lead->contact_name}");

            $numeroWhatsapp = $this->chatwootServices->isWhatsappNumber($lead->contact_number);

            if (!$numeroWhatsapp) {
                Log::error("Número {$lead->contact_number} não é um número de WhatsApp válido para o lead {$lead->contact_name}");
                echo "Número {$lead->contact_number} não é um número de WhatsApp válido para o lead {$lead->contact_name}";
                return;
            }

            try {
                $this->chatwootServices->sendMessage(
                    $numeroWhatsapp,
                    $etapa->message_content,
                    $user->api_post,
                    $user->apikey
                );
                $this->registrarEnvio($lead, $etapa);
                $this->info("Mensagem da etapa {$etapa->id} enviada para o lead {$lead->contact_name}");
            } catch (\Exception $e) {
            Log::error("Erro ao enviar mensagem para o lead {$lead->contact_name}: " . $e->getMessage());
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
