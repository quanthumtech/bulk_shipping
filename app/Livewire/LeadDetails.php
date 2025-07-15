<?php

namespace App\Livewire;

use App\Models\SyncFlowLeads;
use App\Models\Cadencias;
use App\Models\ChatwootConversation;
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
    public $isFromWebhook = false;
    public array $showMessages = []; // Array to track collapse state for each conversation

    public function mount($leadId)
    {
        $this->leadId = $leadId;
        $this->loadLeadData();
    }

    public function toggleMessages($conversationId)
    {
        $this->showMessages[$conversationId] = !($this->showMessages[$conversationId] ?? false);
        Log::info("Messages collapse for conversation ID '{$conversationId}' toggled: " . ($this->showMessages[$conversationId] ? 'Aberto' : 'Fechado'));
    }

    public function loadLeadData()
    {
        try {
            // Carregar o lead com suas relações
            $this->lead = SyncFlowLeads::with(['cadencia', 'chatwootConversations.messages'])
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

            // Verificar se o lead veio via webhook
            $this->isFromWebhook = !empty($this->lead->id_card) && $this->lead->id_card !== 'Não fornecido';

            // Carregar cadência
            $this->cadencia = $this->lead->cadencia;

            // Carregar conversas diretamente da API do Chatwoot
            $chatwootService = app(ChatwootService::class);
            $apiConversations = $chatwootService->getContactConversation(
                $this->lead->contact_id,
                Auth::user()->chatwoot_accoumts,
                Auth::user()->token_acess
            );

            // Inicializar conversas
            $this->conversations = [];
            if (!is_array($apiConversations)) {
                Log::warning('Resposta da API do Chatwoot inválida.', [
                    'lead_id' => $this->leadId,
                    'response' => $apiConversations,
                ]);
                $this->warning('Não foi possível carregar conversas da API.', position: 'toast-top');
            } else {
                foreach ($apiConversations as $conversation) {
                    // Verificar estrutura do payload
                    if (!isset($conversation['id']) || !is_array($conversation)) {
                        Log::warning('Conversa inválida ou sem ID encontrada no payload.', [
                            'conversation' => $conversation,
                            'lead_id' => $this->leadId,
                        ]);
                        continue;
                    }

                    $conversationId = $conversation['id'];
                    // Inicializar estado do colapso
                    if (!isset($this->showMessages[$conversationId])) {
                        $this->showMessages[$conversationId] = false;
                    }

                    // Atualizar contact_id se necessário
                    if (!$this->lead->contact_id && isset($conversation['meta']['sender']['id'])) {
                        $this->lead->contact_id = $conversation['meta']['sender']['id'];
                        $this->lead->save();
                        Log::info('Contact ID atualizado para lead.', [
                            'lead_id' => $this->leadId,
                            'contact_id' => $this->lead->contact_id,
                        ]);
                    }

                    // Sincronizar conversa com o banco
                    $exists = ChatwootConversation::where('conversation_id', $conversationId)
                        ->where('sync_flow_lead_id', $this->lead->id)
                        ->exists();

                    if (!$exists) {
                        try {
                            ChatwootConversation::create([
                                'sync_flow_lead_id' => $this->lead->id,
                                'conversation_id' => $conversationId,
                                'account_id' => Auth::user()->chatwoot_accoumts,
                                'agent_id' => $conversation['meta']['assignee']['id'] ?? null,
                                'status' => $conversation['status'] ?? 'open',
                                'content' => $conversation['messages'][0]['content'] ?? null,
                                'last_activity_at' => Carbon::createFromTimestamp($conversation['last_activity_at'] ?? time())->toDateTimeString(),
                                'agent_assigned_once' => !empty($conversation['meta']['assignee']['id']),
                            ]);

                            // Sincronizar mensagens
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

                    // Adicionar conversa ao array para exibição
                    $this->conversations[] = [
                        'id' => $conversationId,
                        'status' => $conversation['status'] ?? 'open',
                        'created_at' => Carbon::createFromTimestamp($conversation['created_at'] ?? time())->toDateTimeString(),
                        'updated_at' => Carbon::createFromTimestamp($conversation['updated_at'] ?? time())->toDateTimeString(),
                        'assignee_id' => $conversation['meta']['assignee']['id'] ?? null,
                        'assignee_name' => $conversation['meta']['assignee']['name'] ?? 'Não atribuído',
                        'messages' => isset($conversation['messages']) && is_array($conversation['messages'])
                            ? array_map(function ($message) {
                                return [
                                    'message_id' => $message['id'] ?? 'N/A',
                                    'content' => $message['content'] ?? 'Mensagem vazia',
                                    'sender_name' => $message['sender']['name'] ?? ($message['sender_name'] ?? 'Desconhecido'),
                                    'created_at' => Carbon::parse($message['created_at'] ?? now())->format('d/m/Y H:i'),
                                ];
                            }, $conversation['messages'])
                            : [],
                    ];
                }
            }

            // Carregar conversas do banco como fallback
            $this->lead->load('chatwootConversations.messages');
            foreach ($this->lead->chatwootConversations as $dbConversation) {
                if (!collect($this->conversations)->contains('id', $dbConversation->conversation_id)) {
                    $this->conversations[] = [
                        'id' => $dbConversation->conversation_id,
                        'status' => $dbConversation->status,
                        'created_at' => $dbConversation->created_at->toDateTimeString(),
                        'updated_at' => $dbConversation->updated_at->toDateTimeString(),
                        'assignee_id' => $dbConversation->agent_id,
                        'assignee_name' => 'Não atribuído',
                        'messages' => $dbConversation->messages->map(function ($message) {
                            return [
                                'message_id' => $message->message_id,
                                'content' => $message->content,
                                'sender_name' => $message->sender_name ?? 'Desconhecido',
                                'created_at' => $message->created_at->format('d/m/Y H:i'),
                            ];
                        })->toArray(),
                    ];
                    $this->showMessages[$dbConversation->conversation_id] = false;
                }
            }
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
            'isFromWebhook' => $this->isFromWebhook,
        ]);
    }
}