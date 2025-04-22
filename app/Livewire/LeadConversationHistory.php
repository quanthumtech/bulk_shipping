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
        $this->lead = SyncFlowLeads::with(['chatwootConversations.messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])->find($this->leadId);

        if ($this->lead) {
            $this->conversations = $this->lead->chatwootConversations->map(function ($conversation) {
                // Converter last_activity_at para Carbon, se não for uma instância de Carbon
                $lastActivityAt = !($conversation->last_activity_at instanceof Carbon)
                    ? Carbon::parse($conversation->last_activity_at)
                    : $conversation->last_activity_at;

                return [
                    'id' => $conversation->conversation_id,
                    'status' => $conversation->status,
                    'last_activity_at' => $lastActivityAt ? $lastActivityAt->format('d/m/Y H:i') : 'N/A',
                    'messages' => $conversation->messages->map(function ($message) {
                        // created_at já deve ser Carbon, mas verificamos por segurança
                        $createdAt = !($message->created_at instanceof Carbon)
                            ? Carbon::parse($message->created_at)
                            : $message->created_at;

                        return [
                            'message_id' => $message->message_id,
                            'content' => $message->content,
                            'created_at' => $createdAt ? $createdAt->format('d/m/Y H:i') : 'N/A',
                            'is_sent' => $message->message_type === 'outgoing', // Ajuste conforme necessário
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
