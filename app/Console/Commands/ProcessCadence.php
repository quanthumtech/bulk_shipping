<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SyncFlowLeads;
use Carbon\Carbon;
use App\Services\ChatwootService;
use App\Models\CadenceMessage;
use App\Models\Cadencias;
use App\Models\Evolution;
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

                // Pular leads com situação 'Contato Efetivo'
                if ($lead->situacao_contato === 'Contato Efetivo') {
                    Log::info("Lead {$lead->id} possui situação 'Contato Efetivo'. Pulando execução das etapas.");
                    continue;
                }

                // Validar horário da cadência
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

                if (!isset($etapas[$currentEtapaIndex])) {
                    Log::info("Nenhuma etapa pendente para o lead {$lead->id}.");
                    continue;
                }

                $etapa = $etapas[$currentEtapaIndex];

                if (!$etapa->active) {
                    Log::info("Etapa {$etapa->id} do lead {$lead->id} não está ativa. Pulando...");
                    continue;
                }

                // Validar horário da etapa
                if (!$this->isValidTime($etapa->hora)) {
                    Log::warning("Horário inválido para a etapa {$etapa->id} do lead {$lead->id}. Pulando...");
                    continue;
                }

                // Validar etapa->dias
                if (!is_numeric($etapa->dias) || (int)$etapa->dias < 0) {
                    Log::error("Valor inválido para dias na etapa {$etapa->id} do lead {$lead->id}: {$etapa->dias}. Pulando...");
                    continue;
                }

                $dias = (int)$etapa->dias;

                // Validar etapa->created_at
                if (is_null($etapa->created_at)) {
                    Log::error("Etapa {$etapa->id} do lead {$lead->id} não tem created_at. Usando data atual.");
                    $etapaCreatedAt = $now;
                } else {
                    $etapaCreatedAt = $etapa->created_at;
                    if ($etapaCreatedAt->diffInDays($now) > 7) {
                        Log::warning("Etapa {$etapa->id} do lead {$lead->id} tem created_at muito antigo: {$etapaCreatedAt}. Usando data atual.");
                        $etapaCreatedAt = $now;
                    }
                }

                // Definir intervalo de horário permitido
                $horaInicio = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_inicio, $now->timezone)
                    ->setDate($now->year, $now->month, $now->day);
                $horaFim = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_fim, $now->timezone)
                    ->setDate($now->year, $now->month, $now->day);

                $this->info("Intervalo permitido: {$horaInicio} até {$horaFim}");

                // Verificar se a etapa já foi enviada
                if ($this->etapaEnviada($lead, $etapa)) {
                    Log::info("Etapa {$etapa->id} do lead {$lead->id} já foi enviada.");
                    continue;
                }

                // Calcular data e hora agendada para a etapa
                $dataAgendada = $dias == 0
                    ? $now->copy()->setTimeFromTimeString($etapa->hora)
                    : $etapaCreatedAt->copy()->addDays($dias)->setTimeFromTimeString($etapa->hora);

                Log::debug("Horário agendado para a etapa {$etapa->id} do lead {$lead->id}: " . $dataAgendada);
                $this->info("Horário agendado para a etapa {$etapa->id} do lead {$lead->id}: " . $dataAgendada);
                $this->info("Etapa created_at: {$etapaCreatedAt}, Dias: {$etapa->dias}");

                // Verificar se dataAgendada é válida
                if ($dataAgendada->isFuture()) {
                    Log::info("Etapa {$etapa->id} do lead {$lead->id} está agendada para o futuro: {$dataAgendada}. Aguardando...");
                    continue;
                }

                // Verificar se está dentro do intervalo permitido
                if (!$now->between($horaInicio, $horaFim)) {
                    Log::info("Etapa {$etapa->id} do lead {$lead->id} fora do horário permitido ({$lead->cadencia->hora_inicio} - {$lead->cadencia->hora_fim}). Aguardando...");
                    continue;
                }

                // Verificar se a etapa está no dia agendado ou atrasada
                if ($dataAgendada->isSameDay($now)) {
                    // Para o mesmo dia, exigir horário exato (com tolerância)
                    $tolerance = 60; // Tolerância de 60 segundos
                    $dataAgendadaEnd = $dataAgendada->copy()->addSeconds($tolerance);

                    if ($now->between($dataAgendada, $dataAgendadaEnd)) {
                        $this->info("Etapa {$etapa->id} do lead {$lead->id} no horário exato. Processando...");
                        $this->processarEtapa($lead, $etapa);
                    } else {
                        Log::info("Etapa {$etapa->id} do lead {$lead->id} fora da janela de envio no mesmo dia (janela: {$dataAgendada} a {$dataAgendadaEnd}).");
                    }
                } else {
                    // Para dias anteriores, enviar imediatamente dentro do intervalo permitido
                    Log::warning("Etapa {$etapa->id} do lead {$lead->id} está atrasada (agendada para {$dataAgendada}). Processando...");
                    $this->processarEtapa($lead, $etapa);
                }
            }

            $this->info("Ciclo concluído. Aguardando 30 segundos para o próximo ciclo.");
            sleep(30);
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
            Log::error("Formato de horário inválido: {$time}");
            return false;
        }
    }

    protected function processarEtapa($lead, $etapa)
    {
        $this->chatwootServices = new ChatwootService();

        // Buscar a cadência associada à etapa
        $cadencia = Cadencias::find($etapa->cadencia_id);

        if (!$cadencia) {
            Log::error("Cadência não encontrada para a etapa {$etapa->id}");
            return;
        }

        // Buscar a caixa Evolution com base no evolution_id da cadência
        $evolution = Evolution::find($cadencia->evolution_id);

        if ($evolution && $evolution->api_post && $evolution->apikey) {
            Log::info("Processando etapa {$etapa->id} para lead {$lead->contact_name}");

            // Formatá-lo para o padrão: 5512988784433
            $numeroWhatsapp = $this->isWhatsappNumber($lead->contact_number);

            Log::info("Número formatado: {$numeroWhatsapp}");

            try {
                // Enviar mensagem usando as credenciais da caixa Evolution
                $this->chatwootServices->sendMessage(
                    $numeroWhatsapp,
                    $etapa->message_content,
                    $evolution->api_post,
                    $evolution->apikey
                );
                $this->registrarEnvio($lead, $etapa);
                $this->info("Mensagem da etapa {$etapa->id} enviada para o lead {$lead->contact_name}");
            } catch (\Exception $e) {
                Log::error("Erro ao enviar mensagem para o lead {$lead->contact_name}: " . $e->getMessage());
            }
        } else {
            Log::error("Caixa Evolution ou credenciais não encontradas para evolution_id: {$cadencia->evolution_id}");
        }
    }

    protected function isWhatsappNumber($number)
    {
        // Remove todos os caracteres que não sejam dígitos
        $digits = preg_replace('/\D/', '', $number);

        // Se o número não começar com '55', o prefixa
        if (substr($digits, 0, 2) !== '55') {
            $digits = '55' . $digits;
        }

        return $digits;
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
