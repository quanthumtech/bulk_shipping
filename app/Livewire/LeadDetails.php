<?php

namespace App\Livewire;

use App\Models\SyncFlowLeads;
use App\Models\Cadencias;
use App\Models\ChatwootConversation;
use App\Models\WebhookLog;
use App\Services\ChatwootService;
use Livewire\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class LeadDetails extends Component
{
    use Toast;

    public $leadId;
    public $lead;
    public $cadencia;
    public $conversations = [];
    public $logs = [];
    public $isFromWebhook = false;
    public $selectedLogId = null;
    public $showLogDrawer = false;
    public $activeTab = 'lead_info';

    public function mount($leadId)
    {
        $this->leadId = $leadId;
        $this->loadLeadData();
    }

    public function openLogModal(int $id)
    {
        $this->selectedLogId = $id;
        $this->showLogDrawer = true;
    }

    public function closeLogModal()
    {
        $this->selectedLogId = null;
        $this->showLogDrawer = false;
    }

    public function loadLeadData()
    {
        try {
            $this->lead = SyncFlowLeads::with(['cadencia'])
                ->where('id', $this->leadId)
                ->where('chatwoot_accoumts', Auth::user()->chatwoot_accoumts ?? null)
                ->first();

            if (!$this->lead) {
                Log::error('Lead não encontrado ou conta Chatwoot não configurada.', [
                    'lead_id' => $this->leadId,
                    'user_id' => Auth::user()->id ?? null,
                ]);
                $this->error('Lead não encontrado ou conta Chatwoot não configurada.', position: 'toast-top');
                return;
            }

            $this->isFromWebhook = !empty($this->lead->id_card) && $this->lead->id_card !== 'Não fornecido';

            $this->cadencia = $this->lead->cadencia;

            $chatwootService = app(ChatwootService::class);
            $apiConversations = $chatwootService->getContactConversation(
                $this->lead->contact_id ?? null,
                Auth::user()->chatwoot_accoumts,
                Auth::user()->token_acess
            );

            $this->conversations = [];
            if (!is_array($apiConversations)) {
                Log::warning('Resposta da API do Chatwoot inválida.', [
                    'lead_id' => $this->leadId,
                    'response' => $apiConversations,
                ]);
                $this->warning('Não foi possível carregar conversas da API.', position: 'toast-top');
            } else {
                foreach ($apiConversations as $conversation) {
                    if (!is_array($conversation) || !isset($conversation['id'])) {
                        Log::warning('Conversa inválida ou sem ID encontrada no payload.', [
                            'conversation' => $conversation,
                            'lead_id' => $this->leadId,
                        ]);
                        continue;
                    }

                    $conversationId = $conversation['id'];

                    if (!$this->lead->contact_id && isset($conversation['meta']['sender']['id'])) {
                        $this->lead->contact_id = $conversation['meta']['sender']['id'];
                        $this->lead->save();
                        Log::info('Contact ID atualizado para lead.', [
                            'lead_id' => $this->leadId,
                            'contact_id' => $this->lead->contact_id,
                        ]);
                    }

                    $exists = ChatwootConversation::where('conversation_id', $conversationId)
                        ->where('sync_flow_lead_id', $this->lead->id)
                        ->exists();

                    if (!$exists) {
                        try {
                            ChatwootConversation::create([
                                'sync_flow_lead_id' => $this->lead->id,
                                'conversation_id' => $conversationId,
                                'account_id' => Auth::user()->chatwoot_accoumts,
                                'agent_id' => $conversation['meta']['assignee']['id'] ?? $conversation['assignee_id'] ?? null,
                                'status' => $conversation['status'] ?? 'open',
                                'content' => $conversation['messages'][0]['content'] ?? null,
                                'last_activity_at' => Carbon::createFromTimestamp($conversation['last_activity_at'] ?? time())->toDateTimeString(),
                                'agent_assigned_once' => !empty($conversation['meta']['assignee']['id'] ?? $conversation['assignee_id']),
                            ]);

                            if (isset($conversation['messages']) && is_array($conversation['messages'])) {
                                foreach ($conversation['messages'] as $message) {
                                    if (!isset($message['id'])) {
                                        Log::warning('Mensagem sem ID encontrada.', [
                                            'message' => $message,
                                            'conversation_id' => $conversationId,
                                        ]);
                                        continue;
                                    }
                                    \App\Models\ChatwootMessage::updateOrCreate(
                                        [
                                            'chatwoot_conversation_id' => $conversationId,
                                            'message_id' => $message['id'],
                                        ],
                                        [
                                            'content' => $message['content'] ?? 'Mensagem vazia',
                                            'sender_name' => $message['sender']['name'] ?? ($message['sender_name'] ?? 'Desconhecido'),
                                            'sender_type' => $message['sender_type'] ?? null,
                                            'message_type' => $message['message_type'] ?? 1,
                                            'created_at' => Carbon::parse($message['created_at'] ?? now())->toDateTimeString(),
                                        ]
                                    );
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error('Erro ao sincronizar conversa: ' . $e->getMessage(), [
                                'conversation_id' => $conversationId,
                                'lead_id' => $this->lead->id,
                            ]);
                            $this->warning('Erro ao sincronizar conversa. Exibindo dados da API.', position: 'toast-top');
                        }
                    }

                    $this->conversations[] = [
                        'id' => $conversationId,
                        'status' => $conversation['status'] ?? 'open',
                        'created_at' => Carbon::createFromTimestamp($conversation['created_at'] ?? time())->toDateTimeString(),
                        'updated_at' => Carbon::createFromTimestamp($conversation['updated_at'] ?? time())->toDateTimeString(),
                        'assignee_id' => $conversation['meta']['assignee']['id'] ?? $conversation['assignee_id'] ?? null,
                        'assignee_name' => $conversation['meta']['assignee']['name'] ?? ($conversation['assignee_name'] ?? 'Não atribuído'),
                        'assignee_email' => $conversation['meta']['assignee']['email'] ?? ($conversation['assignee_email'] ?? null),
                    ];
                }
            }

            $this->logs = WebhookLog::query()
                ->where('user_id', Auth::user()->id)
                ->where('archived', false)
                ->orderBy('created_at', 'desc')
                ->get()
                ->filter(function ($log) {
                    if (!isset($log->context['steps']) || !is_array($log->context['steps'])) {
                        return false;
                    }

                    foreach ($log->context['steps'] as $step) {
                        if (!isset($step['context']) || !is_array($step['context'])) {
                            continue;
                        }

                        $context = $step['context'];
                        $idCardMatch = isset($context['id_card']) && $context['id_card'] === $this->lead->id_card;
                        $contactNumberMatch = isset($context['contact_number']) && $context['contact_number'] === $this->lead->contact_number;

                        if ($idCardMatch || $contactNumberMatch) {
                            return true;
                        }
                    }

                    return false;
                })
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'type' => $log->type,
                        'webhook_type' => $log->webhook_type ?? 'N/A',
                        'message' => $log->message,
                        'chatwoot_account_id' => $log->chatwoot_account_id ?? 'N/A',
                        'created_at' => Carbon::parse($log->created_at)->format('d/m/Y H:i:s'),
                        'context' => $log->context,
                    ];
                })
                ->values()
                ->toArray();

        } catch (\Exception $e) {
            Log::error('Erro ao carregar dados do lead: ' . $e->getMessage(), [
                'lead_id' => $this->leadId,
                'user_id' => Auth::user()->id ?? null,
            ]);
            $this->error('Erro ao carregar dados do lead.', position: 'toast-top');
        }
    }

    public function render()
    {
        return view('livewire.lead-details', [
            'lead' => $this->lead,
            'cadencia' => $this->cadencia,
            'conversations' => $this->conversations,
            'logs' => $this->logs,
            'isFromWebhook' => $this->isFromWebhook,
            'selectedLog' => $this->selectedLogId ? WebhookLog::find($this->selectedLogId) : null,
        ]);
    }
}