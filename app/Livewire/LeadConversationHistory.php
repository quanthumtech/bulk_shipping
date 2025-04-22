<?php

namespace App\Livewire;

use App\Models\SyncFlowLeads;
use Carbon\Carbon;
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
                $query->select('id', 'name', 'email');
            },
            'chatwootConversations.messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }
        ])->find($this->leadId);

        if ($this->lead) {
            $this->conversations = $this->lead->chatwootConversations->map(function ($conversation) {
                $lastActivityAt = (is_numeric($conversation->last_activity_at) || is_string($conversation->last_activity_at))
                    ? Carbon::createFromTimestamp($conversation->last_activity_at)
                    : $conversation->last_activity_at;

                // Definir o nome do agente para a conversa
                $agentName = $conversation->agent
                    ? ($conversation->agent->name ?? $conversation->agent->email ?? 'Agente Desconhecido')
                    : 'Agente Desconhecido';

                return [
                    'id' => $conversation->conversation_id,
                    'status' => $conversation->status,
                    'last_activity_at' => $lastActivityAt ? $lastActivityAt->format('d/m/Y H:i') : 'N/A',
                    'agent_name' => $agentName, // Incluído explicitamente
                    'messages' => $conversation->messages->map(function ($message) use ($agentName) {
                        $createdAt = (is_numeric($message->created_at) || is_string($message->created_at))
                            ? Carbon::createFromTimestamp($message->created_at)
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
