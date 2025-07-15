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

class LeadDetails extends Component
{
    use Toast;

    public $leadId;
    public $lead;
    public $cadencia;
    public $conversations = [];
    public $isFromWebhook = false;

    public function mount($leadId)
    {
        $this->leadId = $leadId;
        $this->loadLeadData();
    }

    public function loadLeadData()
    {
        // Carregar o lead com suas relações
        $this->lead = SyncFlowLeads::with(['cadencia', 'chatwootConversations.messages'])
            ->where('id', $this->leadId)
            ->where('chatwoot_accoumts', Auth::user()->chatwoot_accoumts)
            ->firstOrFail();

        // Verificar se o lead veio via webhook
        $this->isFromWebhook = !empty($this->lead->id_card) && $this->lead->id_card !== 'Não fornecido';

        // Carregar cadência
        $this->cadencia = $this->lead->cadencia;

        // Carregar e sincronizar conversas do Chatwoot
        if ($this->lead->contact_id) {
            $chatwootService = app(ChatwootService::class);
            $this->conversations = $chatwootService->getContactConversation(
                $this->lead->contact_id,
                Auth::user()->chatwoot_accoumts,
                Auth::user()->token_acess
            );

            // Sincronizar conversas do Chatwoot com o banco
            foreach ($this->conversations as $conversation) {
                $exists = ChatwootConversation::where('conversation_id', $conversation['id'])
                    ->where('sync_flow_lead_id', $this->lead->id)
                    ->exists();

                if (!$exists) {
                    try {
                        ChatwootConversation::create([
                            'sync_flow_lead_id' => $this->lead->id,
                            'conversation_id' => $conversation['id'],
                            'account_id' => Auth::user()->chatwoot_accoumts,
                            'agent_id' => $conversation['assignee_id'] ?? null,
                            'status' => $conversation['status'] ?? 'open',
                            'content' => $conversation['messages'][0]['content'] ?? null,
                            'last_activity_at' => \Carbon\Carbon::parse($conversation['last_activity_at'])->toDateTimeString(),
                            'agent_assigned_once' => !empty($conversation['assignee_id']),
                        ]);

                        // Registrar mensagens no modelo ChatwootMessage
                        foreach ($conversation['messages'] as $message) {
                            \App\Models\ChatwootMessage::updateOrCreate(
                                [
                                    'chatwoot_conversation_id' => $conversation['id'],
                                    'message_id' => $message['message_id'],
                                ],
                                [
                                    'content' => $message['content'],
                                    'sender_name' => $message['sender_name'] ?? null,
                                    'created_at' => \Carbon\Carbon::parse($message['created_at'])->toDateTimeString(),
                                ]
                            );
                        }
                    } catch (\Exception $e) {
                        Log::error('Erro ao sincronizar conversa: ' . $e->getMessage(), [
                            'conversation_id' => $conversation['id'],
                            'lead_id' => $this->lead->id,
                        ]);
                        $this->warning('Erro ao sincronizar conversa. A conversa está sendo criada agora.', position: 'toast-top');
                    }
                }
            }

            // Recarregar conversas do banco para consistência
            $this->lead->load('chatwootConversations.messages');
            $this->conversations = $this->lead->chatwootConversations->map(function ($conversation) {
                return [
                    'id' => $conversation->conversation_id,
                    'status' => $conversation->status,
                    'created_at' => $conversation->created_at->toDateTimeString(),
                    'updated_at' => $conversation->updated_at->toDateTimeString(),
                    'assignee_id' => $conversation->agent_id,
                    'messages' => $conversation->messages->map(function ($message) {
                        return [
                            'message_id' => $message->message_id,
                            'content' => $message->content,
                            'sender_name' => $message->sender_name,
                            'created_at' => $message->created_at->format('d/m/Y H:i'),
                        ];
                    })->toArray(),
                ];
            })->toArray();
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