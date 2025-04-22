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
            'chatwootConversations.agent',
            'chatwootConversations.messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }
        ])->find($this->leadId);

        if ($this->lead) {
            $this->conversations = $this->lead->chatwootConversations->map(function ($conversation) {
                if (is_numeric($conversation->last_activity_at)) {
                    $lastActivityAt = Carbon::createFromTimestamp($conversation->last_activity_at);
                } elseif (is_string($conversation->last_activity_at)) {
                    $lastActivityAt = Carbon::parse($conversation->last_activity_at);
                } else {
                    $lastActivityAt = $conversation->last_activity_at;
                }

                return [
                    'id' => $conversation->conversation_id,
                    'last_activity_at' => $lastActivityAt,
                    'messages' => $conversation->messages->map(function ($message) use ($conversation) {
                        $createdAt = is_string($message->created_at)
                            ? Carbon::parse($message->created_at)
                            : $message->created_at;

                        return [
                            'message_id' => $message->message_id,
                            'content' => $message->content,
                            'created_at' => $createdAt ? $createdAt->format('d/m/Y H:i') : 'N/A',
                            'is_sent' => $message->message_type === 'outgoing',
                            'sender_name' => $message->message_type === 'outgoing'
                                ? ($conversation->agent ? $conversation->agent->name ?? $conversation->agent->email : 'Agente Desconhecido')
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
