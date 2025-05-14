<?php

/**
 * Chatwoot Webhook Controller
 *
 * Eventos suportados:
 *
 * Conversa Criada (conversation_created)
 * Conversa Atualizada (conversation_updated)
 * Status da Conversa Atualizado (conversation_status_updated)
 * Mensagem Criada (message_created)
 * Mensagem Atualizada (message_updated)
 *
 * Regras desse Webhook:
 *
 * - Se o evento for conversation_updated ou message_created, o webhook irá processar a conversa e armazenar os dados.
 * - Se o evento for message_created, o webhook irá verificar se a mensagem é uma resposta do cliente (message_type = 0).
 * - Se for uma resposta do cliente, o webhook irá interromper a cadência e atualizar o CRM.
 * - O webhook irá registrar o histórico de atendimento no Zoho CRM.
 * - O webhook irá atribuir o agente à conversa, se necessário.
 * - O webhook irá abrir a conversa se necessário.
 * - O webhook irá armazenar os dados da conversa na tabela pivot.
 */

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
use Illuminate\Support\Facades\DB;
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

            // Verifica se é um evento suportado
            if (isset($payload['event']) && in_array($payload['event'], ['conversation_updated', 'message_created', 'conversation_status_updated'])) {
                // Extrair o ID da conversa
                $conversationId = $payload['event'] === 'message_created' ? ($payload['conversation']['id'] ?? null) : ($payload['id'] ?? null);

                // Extrair o accountId
                $accountId = $payload['account']['id'] ?? ($payload['messages'][0]['account_id'] ?? null);

                // Extrair informações de contato
                $contactEmail = $payload['meta']['sender']['email'] ?? null;
                $contactPhone = $payload['meta']['sender']['phone_number'] ?? null;

                // Extrair telefone de outros campos, se necessário
                if (!$contactPhone && isset($payload['meta']['sender']['identifier'])) {
                    $identifier = $payload['meta']['sender']['identifier'];
                    if (preg_match('/^(\d+)@s\.whatsapp\.net$/', $identifier, $matches)) {
                        $contactPhone = '+' . $matches[1];
                    }
                } elseif (!$contactPhone && isset($payload['messages'][0]['sender']['phone_number'])) {
                    $contactPhone = $payload['messages'][0]['sender']['phone_number'];
                }

                // Extrair conteúdo da mensagem e verificar se é resposta do cliente
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
                    Log::error('accountId não encontrado no payload', ['conversation_id' => $conversationId]);
                    return response()->json(['status' => 'account_id_missing'], 400);
                }

                if (!$conversationId) {
                    Log::error('conversationId não encontrado no payload', ['payload' => $payload]);
                    return response()->json(['status' => 'conversation_id_missing'], 400);
                }

                // Encontrar o lead
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
                    // Atualizar lead se for resposta do cliente
                    if ($isClientResponse && $lead->id) {
                        $lead->situacao_contato = 'Contato Efetivo';
                        $lead->save();
                        $this->zohoCrmService->updateLeadStatusWhatsApp($lead->id_card, 'Contato Respondido');
                        Log::info("Lead {$lead->id} atualizado como 'Contato Efetivo'.");
                    }
                }

                // Encontrar o agente associado
                $chatWootAgent = null;
                if ($lead) {
                    $chatWootAgent = ChatwootsAgents::where('email', $lead->email_vendedor)->first();
                    if (!$chatWootAgent || $chatWootAgent->chatwoot_account_id != $accountId) {
                        Log::warning('Agente Chatwoot inválido ou accountId não corresponde', [
                            'email_vendedor' => $lead->email_vendedor,
                            'conversation_id' => $conversationId
                        ]);
                        $chatWootAgent = null;
                    }
                }

                // Obter token de acesso
                $user = User::where('chatwoot_accoumts', $accountId)->first();
                $apiToken = $user ? $user->token_acess : null;

                if (!$apiToken) {
                    Log::warning('Token de acesso não encontrado', ['account_id' => $accountId]);
                }

                // Verificar se a conversa já existe e se o agente foi atribuído
                $conversation = ChatwootConversation::where('conversation_id', $conversationId)->first();
                $agentAssignedOnce = $conversation ? $conversation->agent_assigned_once : false;

                // Obter lista de agentes
                $agents = $apiToken ? $this->chatwootServices->getAgents($accountId, $apiToken) : [];
                $matchingAgent = null;
                if ($lead && $chatWootAgent) {
                    $matchingAgent = collect($agents)->firstWhere('email', $lead->email_vendedor);
                }

                // Atribuir agente apenas se não foi atribuído antes
                if ($matchingAgent && $apiToken && !$agentAssignedOnce) {
                    $this->chatwootServices->assignAgentToConversation($accountId, $apiToken, $conversationId, $matchingAgent['agent_id']);
                    Log::info('Agente atribuído à conversa', [
                        'conversation_id' => $conversationId,
                        'agent_id' => $matchingAgent['agent_id']
                    ]);
                }

                // Lidar com status da conversa
                $currentStatus = $payload['status'] ?? 'open';
                if ($payload['event'] === 'conversation_status_updated' && $currentStatus === 'resolved') {
                    Log::info('Conversa marcada como resolvida, nenhuma ação automática será tomada', [
                        'conversation_id' => $conversationId
                    ]);
                } elseif ($apiToken && $currentStatus !== 'open' && $currentStatus !== 'resolved') {
                    $this->chatwootServices->toggleConversationStatus($accountId, $apiToken, $conversationId);
                    Log::info('Conversa reaberta', ['conversation_id' => $conversationId]);
                }

                // Armazenar dados da conversa
                $this->storeConversationData(
                    $lead ? $lead->id : null,
                    $conversationId,
                    $accountId,
                    $matchingAgent ? $matchingAgent['agent_id'] : null,
                    $content,
                    $messageId,
                    !$agentAssignedOnce // Passar flag para marcar agent_assigned_once
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

    private function storeConversationData($leadId, $conversationId, $accountId, $agentId, $content = null, $messageId = null, $markAgentAssigned = false)
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
                        'last_activity_at' => now(),
                        'agent_assigned_once' => $markAgentAssigned ? true : DB::raw('agent_assigned_once') // Atualiza apenas se markAgentAssigned for true
                    ]
                );
            }

            if ($content && $messageId && $conversation) {
                $existingMessage = ChatwootMessage::where('message_id', $messageId)->first();
                if ($existingMessage) {
                    Log::info('Mensagem já processada, ignorando', [
                        'message_id' => $messageId,
                        'conversation_id' => $conversationId
                    ]);
                    return;
                }

                $payload = request()->all();
                $author = 'Sistema';
                if ($payload['event'] === 'message_created') {
                    $author = $payload['sender']['name'] ?? ($payload['sender_type'] === 'User' ? ($payload['sender']['email'] ?? 'Agente') : ($payload['meta']['sender']['name'] ?? 'Cliente'));
                } elseif ($payload['event'] === 'conversation_updated' && isset($payload['messages'][0])) {
                    $author = $payload['messages'][0]['sender']['name'] ?? ($payload['messages'][0]['sender_type'] === 'User' ? ($payload['messages'][0]['sender']['email'] ?? 'Agente') : ($payload['meta']['sender']['name'] ?? 'Cliente'));
                }

                if ($author === $payload['meta']['sender']['phone_number']) {
                    $author = 'Cliente';
                }

                ChatwootMessage::create([
                    'chatwoot_conversation_id' => $conversation->id,
                    'content' => $content,
                    'message_id' => $messageId,
                    'sender_name' => $author,
                ]);

                $formattedMessage = sprintf(
                    "[%s] %s: %s\n",
                    now()->format('Y-m-d H:i:s'),
                    $author,
                    $content
                );

                $lead = SyncFlowLeads::find($leadId);
                if ($lead && $lead->id_card) {
                    $existingHistory = $this->zohoCrmService->getLeadField($lead->id_card, 'Hist_rico_Atendimento') ?? '';
                    $updatedHistory = $existingHistory . $formattedMessage;
                    $this->zohoCrmService->registerHistory($lead->id_card, $updatedHistory);
                } else {
                    Log::warning('Lead ou id_card não encontrado para registrar histórico', [
                        'lead_id' => $leadId,
                        'conversation_id' => $conversationId,
                        'message_id' => $messageId
                    ]);
                }
            }

            Log::info('Conversa armazenada com sucesso', [
                'sync_flow_lead_id' => $leadId,
                'conversation_id' => $conversationId,
                'content' => $content,
                'message_id' => $messageId
            ]);
        } catch (\Exception $exception) {
            Log::error('Falha ao armazenar conversa ou registrar histórico', [
                'sync_flow_lead_id' => $leadId,
                'conversation_id' => $conversationId,
                'content' => $content,
                'message_id' => $messageId,
                'error' => $exception->getMessage()
            ]);
        }
    }
}
