<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SyncFlowLeads;
use Carbon\Carbon;
use App\Services\ChatwootService;
use App\Models\CadenceMessage;
use App\Models\Cadencias;
use App\Models\Evolution;
use App\Events\GenericAudit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use App\Models\System;

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
                Event::dispatch(new GenericAudit('cadence.no_pending', [
                    'message' => 'Nenhuma cadência pendente. Aguardando 5 segundos...',
                    'auditable_type' => System::class,
                    'auditable_id' => 0,
                ]));
                sleep(5);
                continue;
            }

            $this->hasPendingCadences($now)
                ->chunk(100, function ($leads) use ($now) {
                    foreach ($leads as $lead) {
                        Event::dispatch(new GenericAudit('cadence.processing_lead', [
                            'lead_id' => $lead->id,
                            'cadencia_id' => $lead->cadencia_id,
                            'message' => "Processando lead {$lead->id} com cadência ID {$lead->cadencia_id}",
                            'auditable_type' => get_class($lead),
                            'auditable_id' => $lead->id,
                        ]));

                        if (!$lead->cadencia) {
                            Event::dispatch(new GenericAudit('cadence.missing', [
                                'lead_id' => $lead->id,
                                'message' => "Cadência não encontrada para o lead {$lead->id}",
                                'auditable_type' => get_class($lead),
                                'auditable_id' => $lead->id,
                            ]));
                            continue;
                        }

                        if ($lead->situacao_contato === 'Contato Efetivo') {
                            Event::dispatch(new GenericAudit('cadence.skipped', [
                                'lead_id' => $lead->id,
                                'message' => "Lead {$lead->id} possui situação 'Contato Efetivo'. Pulando...",
                                'auditable_type' => get_class($lead),
                                'auditable_id' => $lead->id,
                            ]));
                            continue;
                        }

                        if (!$this->isValidTime($lead->cadencia->hora_inicio) || !$this->isValidTime($lead->cadencia->hora_fim)) {
                            Event::dispatch(new GenericAudit('cadence.invalid_time', [
                                'lead_id' => $lead->id,
                                'message' => "Horário inválido para a cadência do lead {$lead->id}. Pulando...",
                                'auditable_type' => get_class($lead),
                                'auditable_id' => $lead->id,
                            ]));
                            continue;
                        }

                        $etapas = $lead->cadencia->etapas;
                        if ($etapas->isEmpty()) {
                            Event::dispatch(new GenericAudit('cadence.no_active_stages', [
                                'lead_id' => $lead->id,
                                'cadencia_id' => $lead->cadencia_id,
                                'message' => "Nenhuma etapa ativa encontrada para a cadência {$lead->cadencia_id} do lead {$lead->id}. Pulando...",
                                'auditable_type' => get_class($lead),
                                'auditable_id' => $lead->id,
                            ]));
                            continue;
                        }

                        $lastSentEtapa = CadenceMessage::where('sync_flow_leads_id', $lead->id)
                            ->orderBy('etapa_id', 'desc')
                            ->first();

                        if ($lastSentEtapa && $lastSentEtapa->etapa->cadencia_id !== $lead->cadencia_id) {
                            Event::dispatch(new GenericAudit('cadence.reset_stages', [
                                'lead_id' => $lead->id,
                                'message' => "Cadência mudou para o lead {$lead->id}. Reiniciando etapas...",
                                'auditable_type' => get_class($lead),
                                'auditable_id' => $lead->id,
                            ]));
                            $currentEtapaIndex = 0;
                        } else {
                            $currentEtapaIndex = $lastSentEtapa ? $etapas->search(function ($etapa) use ($lastSentEtapa) {
                                return $etapa->id === $lastSentEtapa->etapa_id;
                            }) + 1 : 0;
                        }

                        if (!isset($etapas[$currentEtapaIndex])) {
                            Event::dispatch(new GenericAudit('cadence.all_stages_completed', [
                                'lead_id' => $lead->id,
                                'cadencia_id' => $lead->cadencia_id,
                                'message' => "Todas as etapas da cadência {$lead->cadencia_id} foram concluídas para o lead {$lead->id}. Pulando...",
                                'auditable_type' => get_class($lead),
                                'auditable_id' => $lead->id,
                            ]));
                            continue;
                        }

                        while (isset($etapas[$currentEtapaIndex])) {
                            $etapa = $etapas[$currentEtapaIndex];

                            if (!$etapa->active) {
                                Event::dispatch(new GenericAudit('cadence.inactive_stage', [
                                    'lead_id' => $lead->id,
                                    'etapa_id' => $etapa->id,
                                    'message' => "Etapa {$etapa->id} do lead {$lead->id} não está ativa. Pulando...",
                                    'auditable_type' => get_class($lead),
                                    'auditable_id' => $lead->id,
                                ]));
                                $currentEtapaIndex++;
                                continue;
                            }

                            if ($this->etapaEnviada($lead, $etapa)) {
                                Event::dispatch(new GenericAudit('cadence.stage_already_sent', [
                                    'lead_id' => $lead->id,
                                    'etapa_id' => $etapa->id,
                                    'message' => "Etapa {$etapa->id} do lead {$lead->id} já foi enviada.",
                                    'auditable_type' => get_class($lead),
                                    'auditable_id' => $lead->id,
                                ]));
                                $currentEtapaIndex++;
                                continue;
                            }

                            // Validação: pelo menos hora ou intervalo deve ser válido
                            if (!$this->isValidTime($etapa->hora) && !$this->isValidTime($etapa->intervalo)) {
                                Event::dispatch(new GenericAudit('cadence.invalid_stage_time', [
                                    'lead_id' => $lead->id,
                                    'etapa_id' => $etapa->id,
                                    'message' => "Etapa {$etapa->id} do lead {$lead->id} deve ter pelo menos hora ou intervalo definido. Pulando...",
                                    'details' => [
                                        'hora' => $etapa->hora,
                                        'intervalo' => $etapa->intervalo,
                                        'dias' => $etapa->dias,
                                    ],
                                    'auditable_type' => get_class($lead),
                                    'auditable_id' => $lead->id,
                                ]));
                                $currentEtapaIndex++;
                                continue;
                            }

                            $horaInicio = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_inicio, $now->timezone)
                                ->setDate($now->year, $now->month, $now->day);
                            $horaFim = Carbon::createFromFormat('H:i:s', $lead->cadencia->hora_fim, $now->timezone)
                                ->setDate($now->year, $now->month, $now->day);

                            $dataAgendada = $this->calcularDataAgendada($lead, $etapa, $now);
                            Event::dispatch(new GenericAudit('cadence.stage_scheduled', [
                                'lead_id' => $lead->id,
                                'etapa_id' => $etapa->id,
                                'message' => "Etapa {$etapa->id} do lead {$lead->id} agendada para {$dataAgendada}",
                                'auditable_type' => get_class($lead),
                                'auditable_id' => $lead->id,
                            ]));

                            if ($dataAgendada->isFuture()) {
                                Event::dispatch(new GenericAudit('cadence.stage_future', [
                                    'lead_id' => $lead->id,
                                    'etapa_id' => $etapa->id,
                                    'message' => "Etapa {$etapa->id} do lead {$lead->id} ainda no futuro. Aguardando...",
                                    'auditable_type' => get_class($lead),
                                    'auditable_id' => $lead->id,
                                ]));
                                break;
                            }

                            if ($now->between($horaInicio, $horaFim)) {
                                $this->info("Processando etapa {$etapa->id} do lead {$lead->id}...");
                                $this->processarEtapa($lead, $etapa);
                                $currentEtapaIndex++;
                            } else {
                                Event::dispatch(new GenericAudit('cadence.outside_time_window', [
                                    'lead_id' => $lead->id,
                                    'etapa_id' => $etapa->id,
                                    'message' => "Etapa {$etapa->id} do lead {$lead->id} fora do horário permitido ({$horaInicio} - {$horaFim}).",
                                    'auditable_type' => get_class($lead),
                                    'auditable_id' => $lead->id,
                                ]));
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
            Event::dispatch(new GenericAudit('cadence.invalid_time_format', [
                'time' => $time,
                'message' => "Formato de horário inválido: {$time}",
                'auditable_type' => System::class,
                'auditable_id' => 0,
            ]));
            return false;
        }
    }

    protected function calcularDataAgendada($lead, $etapa, $now)
    {
        $baseDate = $this->getBaseDate($lead, $etapa, $now);
        $dias = (int) ($etapa->dias ?? 0);

        Event::dispatch(new GenericAudit('cadence.calculate_scheduled_date', [
            'lead_id' => $lead->id,
            'etapa_id' => $etapa->id,
            'message' => "Calculando data agendada para etapa {$etapa->id}: baseDate={$baseDate}, dias={$dias}, intervalo={$etapa->intervalo}, hora={$etapa->hora}",
            'auditable_type' => get_class($lead),
            'auditable_id' => $lead->id,
        ]));

        $dataAgendada = $baseDate->copy()->addDays($dias);

        if ($etapa->imediat && $etapa->id === $lead->cadencia->etapas->first()->id) {
            Event::dispatch(new GenericAudit('cadence.immediate_stage', [
                'lead_id' => $lead->id,
                'etapa_id' => $etapa->id,
                'message' => "Etapa {$etapa->id} é imediata, usando hora definida ou atual",
                'auditable_type' => get_class($lead),
                'auditable_id' => $lead->id,
            ]));
            return $dataAgendada->setTimeFromTimeString($etapa->hora ?: $baseDate->toTimeString());
        }

        if ($this->isValidTime($etapa->intervalo)) {
            try {
                $intervalo = Carbon::createFromFormat('H:i:s', $etapa->intervalo);
                $dataAgendada->addHours($intervalo->hour)
                    ->addMinutes($intervalo->minute)
                    ->addSeconds($intervalo->second);
                Event::dispatch(new GenericAudit('cadence.interval_applied', [
                    'lead_id' => $lead->id,
                    'etapa_id' => $etapa->id,
                    'message' => "Intervalo aplicado: {$etapa->intervalo}, nova data={$dataAgendada}",
                    'auditable_type' => get_class($lead),
                    'auditable_id' => $lead->id,
                ]));
            } catch (\Exception $e) {
                Event::dispatch(new GenericAudit('cadence.interval_error', [
                    'lead_id' => $lead->id,
                    'etapa_id' => $etapa->id,
                    'message' => "Erro ao processar intervalo da etapa {$etapa->id}: {$e->getMessage()}",
                    'auditable_type' => get_class($lead),
                    'auditable_id' => $lead->id,
                ]));
            }
        }

        if ($this->isValidTime($etapa->hora)) {
            $dataAgendada->setTimeFromTimeString($etapa->hora);
            Event::dispatch(new GenericAudit('cadence.hour_applied', [
                'lead_id' => $lead->id,
                'etapa_id' => $etapa->id,
                'message' => "Hora definida aplicada: {$etapa->hora}, data final={$dataAgendada}",
                'auditable_type' => get_class($lead),
                'auditable_id' => $lead->id,
            ]));
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
        Event::dispatch(new GenericAudit('cadence.base_date', [
            'lead_id' => $lead->id,
            'etapa_id' => $etapa->id,
            'message' => "Base date para lead {$lead->id}, etapa {$etapa->id}: {$baseDate}",
            'auditable_type' => get_class($lead),
            'auditable_id' => $lead->id,
        ]));
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
            Event::dispatch(new GenericAudit('cadence.missing_for_stage', [
                'etapa_id' => $etapa->id,
                'message' => "Cadência não encontrada para a etapa {$etapa->id}",
                'auditable_type' => get_class($lead),
                'auditable_id' => $lead->id,
            ]));
            return;
        }

        $evolution = Evolution::find($cadencia->evolution_id);

        if ($evolution && $evolution->api_post && $evolution->apikey) {
            Event::dispatch(new GenericAudit('cadence.processing_stage', [
                'lead_id' => $lead->id,
                'etapa_id' => $etapa->id,
                'message' => "Processando etapa {$etapa->id} para lead {$lead->contact_name}",
                'auditable_type' => get_class($lead),
                'auditable_id' => $lead->id,
            ]));

            $numeroWhatsapp = $this->isWhatsappNumber($lead->contact_number);

            Event::dispatch(new GenericAudit('cadence.formatted_number', [
                'lead_id' => $lead->id,
                'etapa_id' => $etapa->id,
                'message' => "Número formatado: {$numeroWhatsapp}",
                'auditable_type' => get_class($lead),
                'auditable_id' => $lead->id,
            ]));

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

                    Event::dispatch(new GenericAudit('cadence.message_sent', [
                        'lead_id' => $lead->id,
                        'etapa_id' => $etapa->id,
                        'message' => "Mensagem da etapa {$etapa->id} enviada para o lead {$lead->contact_name}",
                        'auditable_type' => get_class($lead),
                        'auditable_id' => $lead->id,
                    ]));

                    sleep(5);
                    return;
                } catch (\Exception $e) {
                    Event::dispatch(new GenericAudit('cadence.send_failed', [
                        'lead_id' => $lead->id,
                        'etapa_id' => $etapa->id,
                        'attempt' => $attempt,
                        'message' => "Tentativa {$attempt} falhou para lead {$lead->contact_name}: {$e->getMessage()}",
                        'auditable_type' => get_class($lead),
                        'auditable_id' => $lead->id,
                    ]));
                    if ($attempt === $maxAttempts) {
                        Event::dispatch(new GenericAudit('cadence.send_failed_final', [
                            'lead_id' => $lead->id,
                            'etapa_id' => $etapa->id,
                            'message' => "Falha definitiva ao enviar mensagem para lead {$lead->contact_name}",
                            'auditable_type' => get_class($lead),
                            'auditable_id' => $lead->id,
                        ]));
                        return;
                    }
                    sleep(5);
                    $attempt++;
                }
            }
        } else {
            Event::dispatch(new GenericAudit('cadence.missing_evolution', [
                'etapa_id' => $etapa->id,
                'evolution_id' => $cadencia->evolution_id,
                'message' => "Caixa Evolution ou credenciais não encontradas para evolution_id: {$cadencia->evolution_id}",
                'auditable_type' => get_class($lead),
                'auditable_id' => $lead->id,
            ]));
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
