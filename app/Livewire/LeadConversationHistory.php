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
                // Tratar last_activity_at
                $lastActivityAt = null;
                if ($conversation->last_activity_at) {
                    try {
                        $lastActivityAt = is_string($conversation->last_activity_at)
                            ? Carbon::parse($conversation->last_activity_at)
                            : $conversation->last_activity_at;
                    } catch (\Exception $e) {
                        Log::warning('Erro ao parsear last_activity_at', [
                            'conversation_id' => $conversation->conversation_id,
                            'last_activity_at' => $conversation->last_activity_at,
                            'error' => $e->getMessage(),
                        ]);
                    }
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
                    'id' => $conversation->conversation_id,
                    'status' => $conversation->status ?? 'N/A',
                    'last_activity_at' => $lastActivityAt ? $lastActivityAt->format('d/m/Y H:i') : 'N/A',
                    'agent_name' => $agentName,
                    'messages' => $conversation->messages->map(function ($message) {
                        // Tratar created_at
                        $createdAt = null;
                        if ($message->created_at) {
                            try {
                                $createdAt = is_string($message->created_at)
                                    ? Carbon::parse($message->created_at)
                                    : $message->created_at;
                            } catch (\Exception $e) {
                                Log::warning('Erro ao parsear created_at', [
                                    'message_id' => $message->message_id,
                                    'created_at' => $message->created_at,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        return [
                            'message_id' => $message->message_id,
                            'content' => $message->content ?? 'Mensagem vazia',
                            'created_at' => $createdAt ? $createdAt->format('d/m/Y H:i') : 'N/A',
                            'is_sent' => $message->message_type === 'outgoing',
                            'sender_name' => $message->sender_name ?? ($message->message_type === 'outgoing' ? 'Agente Desconhecido' : ($this->lead->contact_name ?? 'Cliente')),
                        ];
                    })->toArray(),
                ];
            })->sortBy('last_activity_at')->toArray();
        } else {
            $this->error('Lead não encontrado.', position: 'toast-top');
            $this->redirectRoute('sync-flow-leads');
        }
    }

    public function downloadConversationHistory()
    {
        try {
            if (empty($this->conversations)) {
                $this->error('Nenhuma conversa disponível para download.', position: 'toast-top');
                return;
            }

            // Coletar todas as mensagens de todas as conversas
            $messages = collect($this->conversations)
                ->flatMap(function ($conversation) {
                    return collect($conversation['messages'])->map(function ($message) {
                        return [
                            'created_at' => $message['created_at'],
                            'sender_name' => $message['sender_name'],
                            'content' => $message['content'],
                            'timestamp' => Carbon::createFromFormat('d/m/Y H:i', $message['created_at'])->format('Y-m-d H:i:s'),
                        ];
                    });
                })
                ->sortBy('timestamp')
                ->map(function ($message) {
                    return sprintf(
                        "[%s] %s: %s\n",
                        $message['timestamp'],
                        $message['sender_name'],
                        $message['content']
                    );
                })
                ->implode('');

            // Gerar o nome do arquivo
            $fileName = 'conversation_history_lead_' . $this->leadId . '_' . now()->format('Ymd_His') . '.txt';

            // Log para depuração
            Log::info('Gerando arquivo de histórico de conversa para download', [
                'lead_id' => $this->leadId,
                'file_name' => $fileName,
                'message_count' => substr_count($messages, "\n"),
            ]);

            // Retornar a resposta de download
            return response()->streamDownload(function () use ($messages) {
                echo $messages;
            }, $fileName, [
                'Content-Type' => 'text/plain',
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao gerar arquivo de histórico de conversa', [
                'lead_id' => $this->leadId,
                'error' => $e->getMessage(),
            ]);
            $this->error('Erro ao gerar o arquivo de download.', position: 'toast-top');
        }
    }

    public function render()
    {
        return view('livewire.lead-conversation-history');
    }
}
