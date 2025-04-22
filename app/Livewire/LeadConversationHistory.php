<?php

namespace App\Livewire;

use App\Models\SyncFlowLeads;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mary\Traits\Toast;

class LeadConversationHistory extends Component
{
    use Toast;

    public $leadId;
    public $lead;
    public $conversations = [];

    public function mount($leadId)
    {
        $this->leadId = $leadId;
        $this->loadConversations();
    }

    public function loadConversations()
    {
        $this->lead = SyncFlowLeads::with([
            'chatwootConversations.agent' => function ($query) {
                $query->select('id', 'agent_id', 'name', 'email');
            },
            'chatwootConversations.messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }
        ])->find($this->leadId);

        if ($this->lead) {
            $this->conversations = $this->lead->chatwootConversations->map(function ($conversation) {
                if (!($conversation->last_activity_at instanceof Carbon)) {
                    if (is_numeric($conversation->last_activity_at)) {
                        $lastActivityAt = Carbon::createFromTimestamp($conversation->last_activity_at);
                    } else {
                        $lastActivityAt = Carbon::parse($conversation->last_activity_at);
                    }
                } else {
                    $lastActivityAt = $conversation->last_activity_at;
                }

                // Definir o nome do agente para a conversa
                $agentName = $conversation->agent
                    ? ($conversation->agent->name ?? $conversation->agent->email ?? 'Agente Desconhecido')
                    : 'Agente Desconhecido';

                // Log para depuração
                Log::info('Agent Loaded for Conversation', [
                    'conversation_id' => $conversation->conversation_id,
                    'agent_id' => $conversation->agent_id,
                    'agent' => $conversation->agent ? [
                        'id' => $conversation->agent->id,
                        'agent_id' => $conversation->agent->agent_id,
                        'name' => $conversation->agent->name,
                        'email' => $conversation->agent->email,
                    ] : null,
                    'agent_name' => $agentName,
                ]);

                return [
                    'last_activity_at' => $lastActivityAt ? $lastActivityAt->format('d/m/Y H:i') : 'N/A',
                    'agent_name' => $agentName,
                    'messages' => $conversation->messages->map(function ($message) use ($agentName) {
                        $createdAt = is_string($message->created_at)
                            ? Carbon::parse($message->created_at)
                            : $message->created_at;

                        return [
                            'message_id' => $message->message_id,
                            'content' => $message->content,
                            'created_at' => $createdAt ? $createdAt->format('d/m/Y H:i') : 'N/A',
                            'is_sent' => $message->message_type === 'outgoing',
                            'sender_name' => $message->message_type === 'outgoing'
                                ? $agentName
                                : ($this->lead->contact_name ?? 'Lead Desconhecido'),
                        ];
                    })->toArray(),
                ];
            })->sortBy('last_activity_at')->toArray();
        } else {
            $this->error('Lead não encontrado.', position: 'toast-top');
            $this->redirectRoute('sync-flow-leads');
        }
    }

    public function render()
    {
        return view('livewire.lead-conversation-history');
    }
}
