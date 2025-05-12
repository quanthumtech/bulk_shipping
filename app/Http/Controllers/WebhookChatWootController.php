<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\SyncFlowLeads;
use App\Models\ChatwootConversation;
use App\Models\ChatwootMessage;
use App\Models\ChatwootsAgents;
use App\Models\User;
use App\Services\ChatwootService;
use App\Services\ZohoCrmService;
use Illuminate\Support\Facades\Log;

class WebhookChatWootController extends Controller
{
    protected $chatwootServices;
    protected $zohoCrmService;

    public function __construct(ChatwootService $chatwootService, ZohoCrmService $zohoCrmService)
    {
        $this->chatwootServices = $chatwootService;
        $this->zohoCrmService = $zohoCrmService;
    }

    public function handleWebhook(Request $request)
    {
        try {
            $payload = $request->all();

            // Registrar o webhook recebido
            Log::info('Chatwoot Webhook Received', ['payload' => $payload]);

            // Verifica se é um evento de atualização de conversa ou mensagem criada
            if (isset($payload['event']) && in_array($payload['event'], ['conversation_updated', 'message_created'])) {
                // Extrair o ID da conversa
                $conversationId = $payload['event'] === 'message_created' ? ($payload['conversation']['id'] ?? null) : ($payload['id'] ?? null);

                // Extrair o accountId
                $accountId = $payload['account']['id'] ?? ($payload['messages'][0]['account_id'] ?? null);

                // Extrair informações de contato
                $contactEmail = $payload['meta']['sender']['email'] ?? null;
                $contactPhone = $payload['meta']['sender']['phone_number'] ?? null;

                // Se contactPhone for null, tentar extrair de meta.sender.identifier ou messages[0].sender.phone_number
                if (!$contactPhone && isset($payload['meta']['sender']['identifier'])) {
                    // Extrair número de telefone de identifier (ex: 5512988784433@s.whatsapp.net)
                    $identifier = $payload['meta']['sender']['identifier'];
                    if (preg_match('/^(\d+)@s\.whatsapp\.net$/', $identifier, $matches)) {
                        $contactPhone = '+' . $matches[1]; // Adiciona o "+" para formato internacional
                    }
                } elseif (!$contactPhone && isset($payload['messages'][0]['sender']['phone_number'])) {
                    $contactPhone = $payload['messages'][0]['sender']['phone_number'];
                }

                // Extrair o conteúdo da mensagem, messageId e verificar se é message_type 0 (resposta)
                $content = null;
                $messageId = null;
                $isClientResponse = false;
                if ($payload['event'] === 'message_created') {
                    $content = $payload['content'] ?? null;
                    $messageId = $payload['id'] ?? null;
                    $isClientResponse = ($payload['message_type'] ?? '') === 0;
                } elseif ($payload['event'] === 'conversation_updated' && isset($payload['messages'][0]['content'])) {
                    $content = $payload['messages'][0]['content'];
                    $messageId = $payload['messages'][0]['id'] ?? null;
                    $isClientResponse = ($payload['messages'][0]['message_type'] ?? '') === 0;
                }

                if (!$accountId) {
                    Log::error('accountId não encontrado no payload', [
                        'conversation_id' => $conversationId
                    ]);
                    return response()->json(['status' => 'account_id_missing'], 400);
                }

                if (!$conversationId) {
                    Log::error('conversationId não encontrado no payload', [
                        'payload' => $payload
                    ]);
                    return response()->json(['status' => 'conversation_id_missing'], 400);
                }

                Log::info('Processando atualização da conversa', [
                    'conversation_id' => $conversationId,
                    'email' => $contactEmail,
                    'phone' => $contactPhone,
                    'account_id' => $accountId,
                    'content' => $content,
                    'message_id' => $messageId,
                    'is_client_response' => $isClientResponse
                ]);

                // Encontrar o lead em SyncFlowLeads com base no e-mail ou telefone de contato
                $lead = null;
                if ($contactEmail || $contactPhone) {
                    $lead = SyncFlowLeads::where('contact_email', $contactEmail)
                        ->orWhere('contact_number', $contactPhone)
                        ->first();
                }

                if (!$lead) {
                    Log::warning('Lead não encontrado para a conversa', [
                        'conversation_id' => $conversationId,
                        'email' => $contactEmail,
                        'phone' => $contactPhone
                    ]);
                } else {
                    Log::info('Lead encontrado', [
                        'id_do_lead' => $lead->id,
                        'email' => $contactEmail,
                        'telefone' => $contactPhone
                    ]);

                    // Se a mensagem é uma resposta do cliente (message_type = 0), interromper a cadência e atualizar o CRM
                    if ($isClientResponse && $lead->id) {
                        $lead->situacao_contato = 'Contato Efetivo';
                        $lead->save();
                        Log::info("Lead {$lead->id} marcado como 'Contato Efetivo' devido à resposta do cliente.");

                        // Atualizar o campo Status_WhatsApp no Zoho CRM
                        $this->zohoCrmService->updateLeadStatusWhatsApp($lead->id_card, 'Contato Respondido');
                        Log::info("Campo Status_WhatsApp atualizado no Zoho CRM para o lead {$lead->id_card}.");
                    }
                }

                // Encontrar o agente associado ao email_vendedor (se houver lead)
                $chatWootAgent = null;
                if ($lead) {
                    $chatWootAgent = ChatwootsAgents::where('email', $lead->email_vendedor)->first();

                    if (!$chatWootAgent || !$chatWootAgent->chatwoot_account_id) {
                        Log::warning('Agente Chatwoot não encontrado', [
                            'email_vendedor' => $lead->email_vendedor,
                            'conversation_id' => $conversationId
                        ]);
                    } elseif ($chatWootAgent->chatwoot_account_id != $accountId) {
                        Log::warning('accountId do agente não corresponde ao accountId do payload', [
                            'email_vendedor' => $lead->email_vendedor,
                            'agent_account_id' => $chatWootAgent->chatwoot_account_id,
                            'payload_account_id' => $accountId,
                            'conversation_id' => $conversationId
                        ]);
                        return response()->json(['status' => 'account_id_mismatch'], 400);
                    }
                }

                // Obter o token de acesso do usuário
                $user = User::where('chatwoot_accoumts', $accountId)->first();

                if (!$user || !$user->token_acess) {
                    Log::warning('Usuário ou token de acesso não encontrado', [
                        'chatwoot_account_id' => $accountId,
                        'conversation_id' => $conversationId
                    ]);
                }

                $apiToken = $user ? $user->token_acess : null;

                // Obter lista de agentes (se houver token)
                $agents = [];
                if ($apiToken) {
                    $agents = $this->chatwootServices->getAgents($accountId, $apiToken);
                    Log::info('Lista de agentes obtida', [
                        'account_id' => $accountId,
                        'agents_count' => count($agents)
                    ]);
                }

                // Encontrar agente com email_vendedor correspondente (se houver lead e agente)
                $matchingAgent = null;
                if ($lead && $chatWootAgent) {
                    $matchingAgent = collect($agents)->firstWhere('email', $lead->email_vendedor);

                    if (!$matchingAgent) {
                        Log::info('Nenhum agente correspondente encontrado', [
                            'email_vendedor' => $lead->email_vendedor,
                            'conversation_id' => $conversationId,
                            'account_id' => $accountId
                        ]);
                    } elseif (!isset($matchingAgent['agent_id'])) {
                        Log::error('agent_id não encontrado no matchingAgent', [
                            'email_vendedor' => $lead->email_vendedor,
                            'conversation_id' => $conversationId,
                            'matching_agent' => $matchingAgent
                        ]);
                        return response()->json(['status' => 'invalid_agent_data'], 400);
                    }
                }

                // Atribuir agente à conversa (se houver agente e token)
                if ($matchingAgent && $apiToken) {
                    $this->chatwootServices->assignAgentToConversation($accountId, $apiToken, $conversationId, $matchingAgent['agent_id']);
                }

                // Abrir conversa se necessário (se houver token)
                if ($apiToken && ($payload['status'] ?? 'open') !== 'open') {
                    $this->chatwootServices->toggleConversationStatus($accountId, $apiToken, $conversationId);
                }

                // Armazenar dados da conversa na tabela pivot
                $this->storeConversationData(
                    $lead ? $lead->id : null,
                    $conversationId,
                    $accountId,
                    $matchingAgent ? $matchingAgent['agent_id'] : null,
                    $content,
                    $messageId
                );

                return response()->json(['status' => 'ok']);
            }

            return response()->json(['status' => 'invalid_event'], 400);
        } catch (\Exception $e) {
            Log::error('Chatwoot Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function storeConversationData($leadId, $conversationId, $accountId, $agentId, $content = null, $messageId = null)
    {
        try {
            $conversation = null;
            if ($leadId) {
                $conversation = ChatwootConversation::updateOrCreate(
                    [
                        'sync_flow_lead_id' => $leadId,
                        'conversation_id' => $conversationId
                    ],
                    [
                        'account_id' => $accountId,
                        'agent_id' => $agentId,
                        'status' => 'open',
                        'last_activity_at' => now()
                    ]
                );
            }

            if ($content && $messageId && $conversation) {
                ChatwootMessage::create([
                    'chatwoot_conversation_id' => $conversation->id,
                    'content' => $content,
                    'message_id' => $messageId,
                ]);
            }

            Log::info('Conversa armazenada com sucesso', [
                'sync_flow_lead_id' => $leadId,
                'conversation_id' => $conversationId,
                'content' => $content,
                'message_id' => $messageId
            ]);
        } catch (\Exception $exception) {
            Log::error('Falha ao armazenar conversa', [
                'sync_flow_lead_id' => $leadId,
                'conversation_id' => $conversationId,
                'content' => $content,
                'message_id' => $messageId,
                'error' => $exception->getMessage()
            ]);
        }
    }
}
