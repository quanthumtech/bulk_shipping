<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SyncFlowLeads;
use Carbon\Carbon;
use App\Services\ChatwootService;
use App\Models\CadenceMessage;
use App\Models\Cadencias;
use App\Models\Evolution;
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
            $this->info("Horário atual: " . $now);

            if (!$this->hasPendingCadences($now)->exists()) {
                $this->info("Nenhuma cadência pendente. Aguardando 5 segundos...");
                sleep(5);
                continue;
            }

            $this->hasPendingCadences($now)
                ->chunk(100, function ($leads) use ($now) {
                    foreach ($leads as $lead) {
                        Log::info("Processando lead {$lead->id} com cadência ID {$lead->cadencia_id}");

                        if (!$lead->cadencia) {
                            Log::warning("Cadência não encontrada para o lead {$lead->id}");
                            continue;
                        }

                        if ($lead->situacao_contato === 'Contato Efetivo') {
                            Log::info("Lead {$lead->id} possui situação 'Contato Efetivo'. Pulando...");
                            continue;
                        }

                        if ($lead->hasCompletedCadence($lead->cadencia_id)) {
                            Log::info("Cadência {$lead->cadencia_id} já concluída para o lead {$lead->id}. Pulando...");
                            $lead->markCadenceCompleted($lead->cadencia_id);
                            continue;
                        }

                        if (!$this->isValidTime($lead->cadencia->hora_inicio) || !$this->isValidTime($lead->cadencia->hora_fim)) {
                            Log::warning("Horário inválido para a cadência do lead {$lead->id}. Pulando...");
                            continue;
                        }

                        $etapas = $lead->cadencia->etapas->where('active', true);

                        $etapasEnviadas = $etapas->filter(function ($etapa) use ($lead) {
                            return $this->etapaEnviada($lead, $etapa);
                        });

                        if ($etapasEnviadas->count() === $etapas->count() && $etapas->count() > 0) {
                            Log::info("Todas as etapas da cadência {$lead->cadencia_id} foram concluídas para o lead {$lead->id}. Marcando como concluída.");
                            $lead->markCadenceCompleted($lead->cadencia_id);
                            continue;
                        }

                        $lastSentEtapa = CadenceMessage::where('sync_flow_leads_id', $lead->id)
                            ->orderBy('etapa_id', 'desc')
                            ->first();

                        if ($lastSentEtapa && $lastSentEtapa->etapa->cadencia_id !== $lead->cadencia_id) {
                            Log::info("Cadência mudou para o lead {$lead->id}. Reiniciando etapas...");
                            $currentEtapaIndex = 0;
                        } else {
                            $currentEtapaIndex = $lastSentEtapa ? $etapas->search(function ($etapa) use ($lastSentEtapa) {
                                return $etapa->id === $lastSentEtapa->etapa_id;
                            }) + 1 : 0;
                        }

                        if (!isset($etapas[$currentEtapaIndex])) {
                            Log::info("Todas as etapas da cadência {$lead->cadencia_id} foram concluídas para o lead {$lead->id}. Pulando...");
                            continue;
                        }

                        while (isset($etapas[$currentEtapaIndex])) {
                            $etapa = $etapas[$currentEtapaIndex];

                            if (!$etapa->active) {
                                Log::info("Etapa {$etapa->id} do lead {$lead->id} não está ativa. Pulando...");
                                $currentEtapaIndex++;
                                continue;
                            }

                            if ($this->etapaEnviada($lead, $etapa)) {
                                Log::info("Etapa {$etapa->id} do lead {$lead->id} já foi enviada.");
                                $currentEtapaIndex++;
                                continue;
                            }

                            // Validação: pelo menos hora ou intervalo deve ser válido
                            if (!$this->isValidTime($etapa->hora) && !$this->isValidTime($etapa->intervalo)) {
                                Log::error("Etapa {$etapa->id} do lead {$lead->id} deve ter pelo menos hora ou intervalo definido. Pulando...");
                                Log::error("Detalhes: hora={$etapa->hora}, intervalo={$etapa->intervalo}, dias={$etapa->dias}");
                                $currentEtapaIndex++;
                                continue;
                            }

                            $horaInicio = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_inicio, $now->timezone)
                                ->setDate($now->year, $now->month, $now->day);
                            $horaFim = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_fim, $now->timezone)
                                ->setDate($now->year, $now->month, $now->day);

                            $dataAgendada = $this->calcularDataAgendada($lead, $etapa, $now);
                            Log::info("Etapa {$etapa->id} do lead {$lead->id} agendada para {$dataAgendada}");

                            if ($dataAgendada->isFuture()) {
                                Log::info("Etapa {$etapa->id} do lead {$lead->id} ainda no futuro. Aguardando...");
                                break;
                            }

                            if ($now->between($horaInicio, $horaFim)) {
                                $this->info("Processando etapa {$etapa->id} do lead {$lead->id}...");
                                $this->processarEtapa($lead, $etapa);
                                $currentEtapaIndex++;
                            } else {
                                Log::info("Etapa {$etapa->id} do lead {$lead->id} fora do horário permitido ({$horaInicio} - {$horaFim}).");
                                break;
                            }
                        }
                    }
                });

            $this->info("Ciclo concluído. Aguardando 5 segundos para o próximo ciclo.");
            sleep(5);
        }
    }

    protected function hasPendingCadences(Carbon $now)
    {

        return SyncFlowLeads::whereNotNull('cadencia_id')
            ->where('situacao_contato', '!=', 'Contato Efetivo')
            ->whereRaw('JSON_CONTAINS(completed_cadences, ?) = 0', [json_encode('cadencia_id')])
            ->whereHas('cadencia', function ($query) {
                $query->where('active', true);
            })
            ->whereHas('cadencia.etapas', function ($query) use ($now) {
                $query->where('active', true)
                    ->whereRaw('DATE_ADD(created_at, INTERVAL COALESCE(dias, 0) DAY) <= ?', [$now])
                    ->where(function ($query) use ($now) {
                        $query->whereNull('intervalo')
                                ->orWhereRaw('DATE_ADD(DATE_ADD(created_at, INTERVAL COALESCE(dias, 0) DAY), INTERVAL TIME_TO_SEC(COALESCE(intervalo, "00:00:00")) SECOND) <= ?', [$now]);
                    });
            })
            ->with(['cadencia.etapas' => function ($query) {
                $query->where('active', true)->orderBy('id');
            }]);

    }

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

    protected function calcularDataAgendada($lead, $etapa, $now)
    {
        $baseDate = $this->getBaseDate($lead, $etapa, $now);
        $dias = (int) ($etapa->dias ?? 0);

        Log::info("Calculando data agendada para etapa {$etapa->id}: baseDate={$baseDate}, dias={$dias}, intervalo={$etapa->intervalo}, hora={$etapa->hora}");

        $dataAgendada = $baseDate->copy()->addDays($dias);

        if ($etapa->imediat && $etapa->id === $lead->cadencia->etapas->first()->id) {
            Log::info("Etapa {$etapa->id} é imediata, usando hora definida ou atual");
            return $dataAgendada->setTimeFromTimeString($etapa->hora ?: $baseDate->toTimeString());
        }

        if ($this->isValidTime($etapa->intervalo)) {
            try {
                $intervalo = Carbon::createFromFormat('H:i:s', $etapa->intervalo);
                $dataAgendada->addHours($intervalo->hour)
                             ->addMinutes($intervalo->minute)
                             ->addSeconds($intervalo->second);
                Log::info("Intervalo aplicado: {$etapa->intervalo}, nova data={$dataAgendada}");
            } catch (\Exception $e) {
                Log::error("Erro ao processar intervalo da etapa {$etapa->id}: {$e->getMessage()}");
            }
        }

        // Se hora estiver definida, usa-a; caso contrário, mantém o horário da data agendada
        if ($this->isValidTime($etapa->hora)) {
            $dataAgendada->setTimeFromTimeString($etapa->hora);
            Log::info("Hora definida aplicada: {$etapa->hora}, data final={$dataAgendada}");
        }

        return $dataAgendada;
    }

    protected function getBaseDate($lead, $etapa, $now)
    {
        $lastMessage = CadenceMessage::where('sync_flow_leads_id', $lead->id)
            ->whereHas('etapa', function ($query) use ($lead) {
                $query->where('cadencia_id', $lead->cadencia_id);
            })
            ->orderBy('enviado_em', 'desc')
            ->first();

        $baseDate = $lastMessage ? Carbon::parse($lastMessage->enviado_em) : ($lead->created_at ?? $now);
        Log::info("Base date para lead {$lead->id}, etapa {$etapa->id}: {$baseDate}");
        return $baseDate;
    }

    protected function etapaEnviada($lead, $etapa)
    {
        return CadenceMessage::where('sync_flow_leads_id', $lead->id)
            ->where('etapa_id', $etapa->id)
            ->whereHas('etapa', function ($query) use ($lead) {
                $query->where('cadencia_id', $lead->cadencia_id);
            })
            ->exists();
    }

    protected function processarEtapa($lead, $etapa)
    {
        $this->chatwootServices = new ChatwootService();

        $cadencia = Cadencias::find($etapa->cadencia_id);

        if (!$cadencia) {
            Log::error("Cadência não encontrada para a etapa {$etapa->id}");
            return;
        }

        $evolution = Evolution::find($cadencia->evolution_id);

        if ($evolution && $evolution->api_post && $evolution->apikey) {
            Log::info("Processando etapa {$etapa->id} para lead {$lead->contact_name}");

            $numeroWhatsapp = $this->isWhatsappNumber($lead->contact_number);

            Log::info("Número formatado: {$numeroWhatsapp}");

            $maxAttempts = 3;
            $attempt = 1;

            while ($attempt <= $maxAttempts) {
                try {
                    $this->chatwootServices->sendMessage(
                        $numeroWhatsapp,
                        $etapa->message_content,
                        $evolution->api_post,
                        $evolution->apikey,
                        $lead->contact_name,
                        $lead->contact_email,
                        $lead->nome_vendedor,
                    );
                    $this->registrarEnvio($lead, $etapa);
                    $this->info("Mensagem da etapa {$etapa->id} enviada para o lead {$lead->contact_name}");

                    Log::info("Aguardando 5 segundos antes do próximo envio...");
                    sleep(5);

                    return;
                } catch (\Exception $e) {
                    Log::error("Tentativa {$attempt} falhou para lead {$lead->contact_name}: " . $e->getMessage());
                    if ($attempt === $maxAttempts) {
                        Log::error("Falha definitiva ao enviar mensagem para lead {$lead->contact_name}");
                        return;
                    }
                    sleep(5);
                    $attempt++;
                }
            }
        } else {
            Log::error("Caixa Evolution ou credenciais não encontradas para evolution_id: {$cadencia->evolution_id}");
        }
    }

    protected function isWhatsappNumber($number)
    {
        $digits = preg_replace('/\D/', '', $number);

        if (substr($digits, 0, 2) !== '55') {
            $digits = '55' . $digits;
        }

        return $digits;
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
