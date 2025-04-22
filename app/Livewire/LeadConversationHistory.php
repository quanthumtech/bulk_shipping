<?php

namespace App\Livewire;

use App\Models\SyncFlowLeads;
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
        $this->lead = SyncFlowLeads::with(['chatwootConversations.messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])->find($this->leadId);

        if ($this->lead) {
            $this->conversations = $this->lead->chatwootConversations->map(function ($conversation) {
                return [
                    'id' => $conversation->conversation_id,
                    'status' => $conversation->status,
                    'last_activity_at' => $conversation->last_activity_at->format('d/m/Y H:i'),
                    'messages' => $conversation->messages->map(function ($message) {
                        return [
                            'message_id' => $message->message_id,
                            'content' => $message->content,
                            'created_at' => $message->created_at->format('d/m/Y H:i'),
                            'is_sent' => $message->message_type === 'outgoing', // Ajuste conforme sua lógica
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
