<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SyncFlowLeads;
use Carbon\Carbon;
use App\Services\ChatwootService;
use App\Services\SystemLogService;
use App\Models\CadenceMessage;
use App\Models\Cadencias;
use App\Models\Evolution;
use Illuminate\Support\Facades\Log;

class ProcessCadence extends Command
{
    protected $signature = 'cadence:process';
    protected $description = 'Processa os envios de mensagens de cadência conforme as etapas';

    protected $systemLogService;
    protected $chatwootService;

    public function __construct(SystemLogService $systemLogService, ChatwootService $chatwootService)
    {
        parent::__construct();
        $this->systemLogService = $systemLogService;
        $this->chatwootService = $chatwootService;
    }

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
                ->chunk(50, function ($leads) use ($now) {  // Reduzido para 50 para melhor performance
                    foreach ($leads as $lead) {
                        Log::info("Processando lead {$lead->id} com cadência ID {$lead->cadencia_id}");
                        $this->systemLogService->info("Processando lead", [
                            'process' => 'ProcessCadencia',
                            'lead_id' => $lead->id,
                            'cadencia_id' => $lead->cadencia_id
                        ]);

                        if (!$lead->cadencia) {
                            Log::warning("Cadência não encontrada para o lead {$lead->id}");
                            $this->systemLogService->warning("Cadência não encontrada para o lead", [
                                'process' => 'ProcessCadencia',
                                'lead_id' => $lead->id
                            ]);
                            continue;
                        }

                        if ($lead->situacao_contato === 'Contato Efetivo') {
                            Log::info("Lead {$lead->id} possui situação 'Contato Efetivo'. Pulando...");
                            $this->systemLogService->info("Lead possui situação 'Contato Efetivo'. Pulando...", [
                                'process' => 'ProcessCadencia',
                                'lead_id' => $lead->id
                            ]);
                            continue;
                        }

                        if ($lead->hasCompletedCadence($lead->cadencia_id)) {
                            Log::info("Cadência {$lead->cadencia_id} já concluída para o lead {$lead->id}. Pulando...");
                            $this->systemLogService->info("Cadência já concluída para o lead. Pulando...", [
                                'process' => 'ProcessCadencia',
                                'lead_id' => $lead->id,
                                'cadencia_id' => $lead->cadencia_id
                            ]);
                            $lead->markCadenceCompleted($lead->cadencia_id);
                            continue;
                        }

                        if (!$this->isValidTime($lead->cadencia->hora_inicio) || !$this->isValidTime($lead->cadencia->hora_fim)) {
                            Log::warning("Horário inválido para a cadência do lead {$lead->id}. Pulando...");
                            $this->systemLogService->warning("Horário inválido para a cadência do lead. Pulando...", [
                                'process' => 'ProcessCadencia',
                                'lead_id' => $lead->id,
                                'cadencia_id' => $lead->cadencia_id
                            ]);
                            continue;
                        }

                        if (!$this->isValidDate($now, $lead->cadencia)) {
                            Log::info("Hoje não é um dia válido para a cadência {$lead->cadencia_id}. Pulando lead {$lead->id}...");
                            $this->systemLogService->info("Hoje não é um dia válido para a cadência. Pulando lead...", [
                                'process' => 'ProcessCadencia',
                                'lead_id' => $lead->id,
                                'cadencia_id' => $lead->cadencia_id
                            ]);
                            continue;
                        }

                        $etapas = $lead->cadencia->etapas->where('active', true)->values();  // values() para índices numéricos

                        $this->systemLogService->info('Etapas ativas encontradas', [
                            'process' => 'ProcessCadencia',
                            'lead_id' => $lead->id,
                            'cadencia_id' => $lead->cadencia_id,
                            'count' => $etapas->count(),
                            'etapa_ids' => $etapas->pluck('id')->toArray()
                        ]);

                        $etapasEnviadas = $etapas->filter(function ($etapa) use ($lead) {
                            return $this->etapaEnviada($lead, $etapa);
                        });

                        if ($etapasEnviadas->count() === $etapas->count() && $etapas->count() > 0) {
                            Log::info("Todas as etapas da cadência {$lead->cadencia_id} foram concluídas para o lead {$lead->id}. Marcando como concluída.");
                            
                            $this->systemLogService->info("Todas as etapas da cadência foram concluídas para o lead. Marcando como concluída.", [
                                'process' => 'ProcessCadencia',
                                'lead_id' => $lead->id,
                                'cadencia_id' => $lead->cadencia_id
                            ]);
                            
                            $lead->markCadenceCompleted($lead->cadencia_id);
                            continue;
                        }

                        $currentEtapaIndex = $this->getCurrentEtapaIndex($lead, $etapas);

                        if ($currentEtapaIndex >= $etapas->count()) {
                            Log::info("Todas as etapas da cadência {$lead->cadencia_id} foram concluídas para o lead {$lead->id}. Pulando...");
                            $this->systemLogService->info("Todas as etapas da cadência foram concluídas para o lead. Pulando...", [
                                'process' => 'ProcessCadencia',
                                'lead_id' => $lead->id,
                                'cadencia_id' => $lead->cadencia_id
                            ]);
                            continue;
                        }

                        while ($currentEtapaIndex < $etapas->count()) {
                            $etapa = $etapas[$currentEtapaIndex];

                            if (!$etapa->active) {
                                Log::info("Etapa {$etapa->id} do lead {$lead->id} não está ativa. Pulando...");
                                $this->systemLogService->info("Etapa não está ativa. Pulando...", [
                                    'process' => 'ProcessCadencia',
                                    'lead_id' => $lead->id,
                                    'etapa_id' => $etapa->id
                                ]);
                                $currentEtapaIndex++;
                                continue;
                            }

                            if ($this->etapaEnviada($lead, $etapa)) {
                                Log::info("Etapa {$etapa->id} do lead {$lead->id} já foi enviada.");
                                $this->systemLogService->info("Etapa já foi enviada.", [
                                    'process' => 'ProcessCadencia',
                                    'lead_id' => $lead->id,
                                    'etapa_id' => $etapa->id
                                ]);
                                $currentEtapaIndex++;
                                continue;
                            }

                            // Validação atualizada: pule se NÃO imediato e sem hora/intervalo
                            if (!$etapa->imediat && !$this->isValidTime($etapa->hora) && !$this->isValidTime($etapa->intervalo)) {
                                Log::error("Etapa {$etapa->id} do lead {$lead->id} (não imediata) deve ter pelo menos hora ou intervalo definido. Pulando...");
                                Log::error("Detalhes: hora={$etapa->hora}, intervalo={$etapa->intervalo}, dias={$etapa->dias}");

                                $this->systemLogService->error("Etapa (não imediata) deve ter pelo menos hora ou intervalo definido. Pulando...", [
                                    'process' => 'ProcessCadencia',
                                    'lead_id' => $lead->id,
                                    'etapa_id' => $etapa->id,
                                    'hora' => $etapa->hora,
                                    'intervalo' => $etapa->intervalo,
                                    'dias' => $etapa->dias
                                ]);
                                $currentEtapaIndex++;
                                continue;
                            }

                            $horaInicio = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_inicio, $now->timezone)
                                ->setDate($now->year, $now->month, $now->day);
                            $horaFim = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_fim, $now->timezone)
                                ->setDate($now->year, $now->month, $now->day);

                            $dataAgendada = $this->calcularDataAgendada($lead, $etapa, $now);
                            Log::info("Etapa {$etapa->id} do lead {$lead->id} agendada para {$dataAgendada}");
                            $this->systemLogService->info("Etapa {$etapa->id} do lead {$lead->id} agendada para {$dataAgendada}", [
                                'process' => 'ProcessCadencia',
                                'lead_id' => $lead->id,
                                'etapa_id' => $etapa->id,
                                'data_agendada' => $dataAgendada->toDateTimeString(),
                                'imediat' => $etapa->imediat ? 'sim' : 'não'
                            ]);

                            if ($dataAgendada->isFuture()) {
                                Log::info("Etapa {$etapa->id} do lead {$lead->id} ainda no futuro. Aguardando...");
                                $this->systemLogService->info("Etapa ainda no futuro. Aguardando...", [
                                    'process' => 'ProcessCadencia',
                                    'lead_id' => $lead->id,
                                    'etapa_id' => $etapa->id,
                                    'data_agendada' => $dataAgendada->toDateTimeString()
                                ]);
                                break;
                            }

                            if ($now->between($horaInicio, $horaFim)) {
                                $this->info("Processando etapa {$etapa->id} do lead {$lead->id}...");
                                $this->systemLogService->info("Processando etapa", [
                                    'process' => 'ProcessCadencia',
                                    'lead_id' => $lead->id,
                                    'etapa_id' => $etapa->id,
                                    'imediat' => $etapa->imediat ? 'sim' : 'não'
                                ]);
                                $this->processarEtapa($lead, $etapa);
                                $currentEtapaIndex++;
                            } else {
                                Log::info("Etapa {$etapa->id} do lead {$lead->id} fora do horário permitido ({$horaInicio} - {$horaFim}).");
                                $this->systemLogService->info("Etapa fora do horário permitido", [
                                    'process' => 'ProcessCadencia',
                                    'lead_id' => $lead->id,
                                    'etapa_id' => $etapa->id,
                                    'hora_inicio' => $horaInicio->toTimeString(),
                                    'hora_fim' => $horaFim->toTimeString()
                                ]);
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
        $query = SyncFlowLeads::whereNotNull('cadencia_id')
            ->where('situacao_contato', '!=', 'Contato Efetivo')
            ->whereRaw('JSON_CONTAINS(completed_cadences, ?) = 0', [json_encode('cadencia_id')])
            ->whereHas('cadencia', function ($query) {
                $query->where('active', true);
            })
            ->whereHas('cadencia.etapas', function ($query) use ($now) {
                $query->where('active', true)
                    ->where(function ($subQuery) use ($now) {
                        // Para não-imediato: usa subquery para base real (último enviado ou created_at)
                        $subQuery->where(function ($innerQuery) use ($now) {
                            $innerQuery->whereRaw(
                                'DATE_ADD(
                                    COALESCE(
                                        (SELECT MAX(enviado_em) FROM cadence_messages WHERE etapa_id = etapas.id AND sync_flow_leads_id = sync_flow_leads.id),
                                        etapas.created_at
                                    ), 
                                    INTERVAL COALESCE(etapas.dias, 0) DAY
                                ) <= ?', 
                                [$now]
                            )
                            ->where(function ($q) use ($now) {
                                $q->whereNull('etapas.intervalo')
                                  ->orWhereRaw(
                                      'DATE_ADD(
                                          DATE_ADD(
                                              COALESCE(
                                                  (SELECT MAX(enviado_em) FROM cadence_messages WHERE etapa_id = etapas.id AND sync_flow_leads_id = sync_flow_leads.id),
                                                  etapas.created_at
                                              ), 
                                              INTERVAL COALESCE(etapas.dias, 0) DAY
                                          ), 
                                          INTERVAL TIME_TO_SEC(COALESCE(etapas.intervalo, "00:00:00")) SECOND
                                      ) <= ?', 
                                      [$now]
                                  );
                            });
                        })
                        // OU para imediato: capturar se imediat=true (check sent/range no loop)
                        ->orWhere('imediat', true);
                    });
            })
            ->with(['cadencia.etapas' => function ($query) {
                $query->where('active', true)->orderBy('id');
            }]);

        $this->systemLogService->info('Verificando cadências pendentes', [
            'process' => 'ProcessCadencia',
            'timestamp' => $now->toDateTimeString()
        ]);

        return $query;
    }

    protected function getCurrentEtapaIndex($lead, $etapas)
    {
        // Refatorado para robustez: busca último enviado na cadência atual
        $lastSentEtapaId = CadenceMessage::where('sync_flow_leads_id', $lead->id)
            ->join('etapas', 'cadence_messages.etapa_id', '=', 'etapas.id')
            ->where('etapas.cadencia_id', $lead->cadencia_id)
            ->orderBy('etapas.id', 'desc')
            ->value('etapas.id');

        if (!$lastSentEtapaId) {
            return 0;
        }

        foreach ($etapas as $index => $etapa) {
            if ($etapa->id === $lastSentEtapaId) {
                return $index + 1;
            }
        }

        // Se mudou cadência ou inválido, reinicia
        Log::info("Reiniciando índice de etapas para lead {$lead->id} (mudança detectada).");
        $this->systemLogService->info("Reiniciando etapas por mudança de cadência", [
            'process' => 'ProcessCadencia',
            'lead_id' => $lead->id,
            'cadencia_id' => $lead->cadencia_id
        ]);
        return 0;
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
            $this->systemLogService->error("Formato de horário inválido", [
                'process' => 'ProcessCadencia',
                'time' => $time,
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    protected function calcularDataAgendada($lead, $etapa, $now)
    {
        $baseDate = $this->getBaseDate($lead, $etapa, $now);
        $dias = (int) ($etapa->dias ?? 0);

        $this->systemLogService->info("Calculando data agendada para etapa", [
            'process' => 'ProcessCadencia',
            'lead_id' => $lead->id,
            'etapa_id' => $etapa->id,
            'base_date' => $baseDate->toDateTimeString(),
            'dias' => $dias,
            'intervalo' => $etapa->intervalo,
            'hora' => $etapa->hora,
            'imediat' => $etapa->imediat
        ]);

        $dataAgendada = $baseDate->copy()->addDays($dias);

        if ($etapa->imediat) {
            // Lógica generalizada para imediato (qualquer etapa): agora ajustado ao range
            $this->systemLogService->info("Etapa imediata: usando horário atual ajustado ao range da cadência", [
                'process' => 'ProcessCadencia',
                'lead_id' => $lead->id,
                'etapa_id' => $etapa->id,
                'hora_range_inicio' => $lead->cadencia->hora_inicio,
                'hora_range_fim' => $lead->cadencia->hora_fim
            ]);
            
            $horaInicio = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_inicio);
            $horaFim = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_fim);
            
            $hojeInicio = $now->copy()->setTimeFromTimeString($lead->cadencia->hora_inicio);
            $hojeFim = $now->copy()->setTimeFromTimeString($lead->cadencia->hora_fim);
            
            if ($now->between($hojeInicio, $hojeFim)) {
                $dataAgendada = $now->copy();  // Agora se no range
            } else {
                // Próximo válido: amanhã no início se após fim, ou hoje no início se antes
                if ($now->greaterThan($hojeFim)) {
                    $dataAgendada = $now->copy()->addDay()->setTimeFromTimeString($lead->cadencia->hora_inicio);
                } else {
                    $dataAgendada = $hojeInicio;
                }
            }
            
            // Hora específica override se setada
            if ($this->isValidTime($etapa->hora)) {
                $dataAgendada->setTimeFromTimeString($etapa->hora);
            }
            
            // Intervalo independente: aplica como delay extra (ex: buffer ou retry em falhas)
            if ($this->isValidTime($etapa->intervalo)) {
                $intervalo = Carbon::createFromFormat('H:i:s', $etapa->intervalo);
                $dataAgendada->addHours($intervalo->hour)
                             ->addMinutes($intervalo->minute)
                             ->addSeconds($intervalo->second);
                $this->systemLogService->info("Intervalo aplicado em imediato (delay extra)", [
                    'process' => 'ProcessCadencia',
                    'lead_id' => $lead->id,
                    'etapa_id' => $etapa->id,
                    'intervalo' => $etapa->intervalo,
                    'nova_data' => $dataAgendada->toDateTimeString()
                ]);
            }
        } else {
            // Lógica original para não-imediato
            if ($this->isValidTime($etapa->intervalo)) {
                try {
                    $intervalo = Carbon::createFromFormat('H:i:s', $etapa->intervalo);
                    $dataAgendada->addHours($intervalo->hour)
                                 ->addMinutes($intervalo->minute)
                                 ->addSeconds($intervalo->second);
                    $this->systemLogService->info("Intervalo aplicado", [
                        'process' => 'ProcessCadencia',
                        'lead_id' => $lead->id,
                        'etapa_id' => $etapa->id,
                        'intervalo' => $etapa->intervalo,
                        'nova_data' => $dataAgendada->toDateTimeString()
                    ]);
                } catch (\Exception $e) {
                    $this->systemLogService->error("Erro ao processar intervalo da etapa", [
                        'process' => 'ProcessCadencia',
                        'lead_id' => $lead->id,
                        'etapa_id' => $etapa->id,
                        'exception' => $e->getMessage()
                    ]);
                }
            }

            if ($this->isValidTime($etapa->hora)) {
                $dataAgendada->setTimeFromTimeString($etapa->hora);
                $this->systemLogService->info("Hora definida aplicada", [
                    'process' => 'ProcessCadencia',
                    'lead_id' => $lead->id,
                    'etapa_id' => $etapa->id,
                    'hora' => $etapa->hora,
                    'data_final' => $dataAgendada->toDateTimeString()
                ]);
            }
        }

        // Ajustar para a próxima data válida respeitando days_of_week e excluded_dates (lógica original)
        $cadencia = $lead->cadencia;
        $loopCount = 0;
        $maxLoops = 365;

        while (!$this->isValidDate($dataAgendada, $cadencia)) {
            if ($loopCount >= $maxLoops) {
                Log::error("Limite de loops atingido ao ajustar data agendada para etapa {$etapa->id} do lead {$lead->id}.");
                $this->systemLogService->error("Limite de loops atingido ao ajustar data agendada", [
                    'process' => 'ProcessCadencia',
                    'lead_id' => $lead->id,
                    'etapa_id' => $etapa->id,
                    'data_agendada' => $dataAgendada->toDateTimeString()
                ]);
                break;
            }

            $dataAgendada->addDay();
            if ($this->isValidTime($etapa->hora)) {
                $dataAgendada->setTimeFromTimeString($etapa->hora);
            }

            $loopCount++;
        }

        if ($loopCount > 0) {
            $this->systemLogService->info("Data agendada ajustada para data válida após {$loopCount} iterações", [
                'process' => 'ProcessCadencia',
                'lead_id' => $lead->id,
                'etapa_id' => $etapa->id,
                'data_ajustada' => $dataAgendada->toDateTimeString()
            ]);
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
        $this->systemLogService->info("Base date para lead e etapa", [
            'process' => 'ProcessCadencia',
            'lead_id' => $lead->id,
            'etapa_id' => $etapa->id,
            'base_date' => $baseDate->toDateTimeString()
        ]);
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

    protected function isValidDate(Carbon $date, Cadencias $cadencia)
    {
        $weekday = $date->dayOfWeekIso;
        $dateStr = $date->format('Y-m-d');

        $daysOfWeek = $cadencia->days_of_week ?? [1, 2, 3, 4, 5, 6, 7];
        $excludedDates = $cadencia->excluded_dates ?? [];

        return in_array($weekday, $daysOfWeek) && !in_array($dateStr, $excludedDates);
    }

    protected function processarEtapa($lead, $etapa)
    {
        $cadencia = Cadencias::find($etapa->cadencia_id);

        if (!$cadencia) {
            $this->systemLogService->error("Cadência não encontrada para a etapa", [
                'process' => 'ProcessCadencia',
                'etapa_id' => $etapa->id
            ]);
            return;
        }

        $evolution = Evolution::find($cadencia->evolution_id);

        if ($evolution && $evolution->api_post && $evolution->apikey) {
            $this->systemLogService->info("Processando etapa para lead", [
                'process' => 'ProcessCadencia',
                'lead_id' => $lead->id,
                'etapa_id' => $etapa->id,
                'contact_name' => $lead->contact_name,
                'imediat' => $etapa->imediat ? 'sim' : 'não'
            ]);

            $numeroWhatsapp = $this->isWhatsappNumber($lead->contact_number);

            $this->systemLogService->info("Número formatado", [
                'process' => 'ProcessCadencia',
                'lead_id' => $lead->id,
                'etapa_id' => $etapa->id,
                'numero_whatsapp' => $numeroWhatsapp
            ]);

            $maxAttempts = 3;
            $attempt = 1;
            $backoff = 5;  // Backoff exponencial para retries

            while ($attempt <= $maxAttempts) {
                try {
                    $this->chatwootService->sendMessage(
                        $numeroWhatsapp,
                        $etapa->message_content,
                        $evolution->api_post,
                        $evolution->apikey,
                        $lead->contact_name,
                        $lead->contact_email,
                        $lead->nome_vendedor
                    );
                    $this->registrarEnvio($lead, $etapa);
                    $this->info("Mensagem da etapa {$etapa->id} enviada para o lead {$lead->contact_name}");
                    $this->systemLogService->info("Mensagem da etapa enviada para o lead", [
                        'process' => 'ProcessCadencia',
                        'lead_id' => $lead->id,
                        'etapa_id' => $etapa->id,
                        'contact_name' => $lead->contact_name
                    ]);

                    // Pause ajustado para imediato (menor para não floodar)
                    $pause = $etapa->imediat ? 2 : 5;
                    $this->systemLogService->info("Aguardando {$pause} segundos antes do próximo envio...", [
                        'process' => 'ProcessCadencia',
                        'lead_id' => $lead->id,
                        'etapa_id' => $etapa->id
                    ]);
                    sleep($pause);

                    return;
                } catch (\Exception $e) {
                    $this->systemLogService->error("Tentativa {$attempt} falhou para lead", [
                        'process' => 'ProcessCadencia',
                        'lead_id' => $lead->id,
                        'etapa_id' => $etapa->id,
                        'contact_name' => $lead->contact_name,
                        'attempt' => $attempt,
                        'exception' => $e->getMessage(),
                        'imediat' => $etapa->imediat ? 'sim' : 'não'
                    ]);
                    if ($attempt === $maxAttempts) {
                        $this->systemLogService->error("Falha definitiva ao enviar mensagem para lead", [
                            'process' => 'ProcessCadencia',
                            'lead_id' => $lead->id,
                            'etapa_id' => $etapa->id,
                            'contact_name' => $lead->contact_name
                        ]);
                        return;
                    }
                    sleep($backoff * $attempt);  // 5s, 10s, 15s
                    $attempt++;
                }
            }
        } else {
            $this->systemLogService->error("Caixa Evolution ou credenciais não encontradas", [
                'process' => 'ProcessCadencia',
                'etapa_id' => $etapa->id,
                'evolution_id' => $cadencia->evolution_id
            ]);
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