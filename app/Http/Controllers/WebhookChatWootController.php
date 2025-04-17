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
use Illuminate\Support\Facades\Log;

class WebhookChatWootController extends Controller
{
    protected $chatwootServices;

    public function handleWebhook(Request $request)
    {
        try {
            $payload = $request->all();

            $this->chatwootServices = new ChatwootService();

            // Registrar o webhook recebido
            Log::info('Chatwoot Webhook Received', ['payload' => $payload]);

            // Verifica se é um evento de atualização de conversa ou mensagem criada
            if (isset($payload['event']) && in_array($payload['event'], ['conversation_updated', 'message_created'])) {
                $conversationId = $payload['id'];
                $contactEmail = $payload['meta']['sender']['email'] ?? null;
                $contactPhone = $payload['meta']['sender']['phone_number'] ?? null;
                $accountId = $payload['account']['id'] ?? null;

                // Extrair o conteúdo da mensagem e messageId com base no tipo de evento
                $content = null;
                $messageId = null;
                if ($payload['event'] === 'message_created') {
                    $content = $payload['content'] ?? null;
                    $messageId = $payload['id'] ?? null;
                } elseif ($payload['event'] === 'conversation_updated' && isset($payload['messages'][0]['content'])) {
                    $content = $payload['messages'][0]['content'];
                    $messageId = $payload['messages'][0]['id'] ?? null;
                }

                if (!$accountId) {
                    Log::error('accountId não encontrado no payload', [
                        'conversation_id' => $conversationId
                    ]);
                    return response()->json(['status' => 'account_id_missing'], 400);
                }

                Log::info('Processando atualização da conversa', [
                    'conversation_id' => $conversationId,
                    'email' => $contactEmail,
                    'phone' => $contactPhone,
                    'account_id' => $accountId,
                    'content' => $content,
                    'message_id' => $messageId
                ]);

                // Encontrar o lead em SyncFlowLeads com base no e-mail ou telefone de contato
                $lead = SyncFlowLeads::where('contact_email', $contactEmail)
                    ->orWhere('contact_number', $contactPhone)
                    ->first();

                if (!$lead) {
                    Log::warning('Lead não encontrado para a conversa', [
                        'conversation_id' => $conversationId,
                        'email' => $contactEmail,
                        'phone' => $contactPhone
                    ]);
                    return response()->json(['status' => 'lead_not_found'], 404);
                }

                Log::info('Lead encontrado', [
                    'id_do_lead' => $lead->id,
                    'email' => $contactEmail,
                    'telefone' => $contactPhone
                ]);

                // Encontrar o agente associado ao email_vendedor
                $chatWootAgent = ChatwootsAgents::where('email', $lead->email_vendedor)->first();

                if (!$chatWootAgent || !$chatWootAgent->chatwoot_account_id) {
                    Log::warning('Agente Chatwoot não encontrado', [
                        'email_vendedor' => $lead->email_vendedor,
                        'conversation_id' => $conversationId
                    ]);
                    return response()->json(['status' => 'chatwoot_agent_not_found'], 404);
                }

                // Validar se o accountId do agente corresponde ao accountId do payload
                if ($chatWootAgent->chatwoot_account_id != $accountId) {
                    Log::warning('accountId do agente não corresponde ao accountId do payload', [
                        'email_vendedor' => $lead->email_vendedor,
                        'agent_account_id' => $chatWootAgent->chatwoot_account_id,
                        'payload_account_id' => $accountId,
                        'conversation_id' => $conversationId
                    ]);
                    return response()->json(['status' => 'account_id_mismatch'], 400);
                }

                // Obter o token de acesso do usuário
                $user = User::where('chatwoot_accoumts', $chatWootAgent->chatwoot_account_id)->first();

                if (!$user || !$user->token_acess) {
                    Log::warning('Usuário ou token de acesso não encontrado', [
                        'chatwoot_account_id' => $chatWootAgent->chatwoot_account_id,
                        'conversation_id' => $conversationId
                    ]);
                    return response()->json(['status' => 'user_or_token_not_found'], 404);
                }

                $apiToken = $user->token_acess;

                // Obter lista de agentes
                $agents = $this->chatwootServices->getAgents($accountId, $apiToken);

                Log::info('Lista de agentes obtida', [
                    'account_id' => $accountId,
                    'agents_count' => count($agents)
                ]);

                // Encontrar agente com email_vendedor correspondente
                $matchingAgent = collect($agents)->firstWhere('email', $lead->email_vendedor);

                if (!$matchingAgent) {
                    Log::info('Nenhum agente correspondente encontrado', [
                        'email_vendedor' => $lead->email_vendedor,
                        'conversation_id' => $conversationId,
                        'account_id' => $accountId
                    ]);
                    return response()->json(['status' => 'agent_not_found'], 404);
                }

                // Validar se o agent_id está presente
                if (!isset($matchingAgent['agent_id'])) {
                    Log::error('agent_id não encontrado no matchingAgent', [
                        'email_vendedor' => $lead->email_vendedor,
                        'conversation_id' => $conversationId,
                        'matching_agent' => $matchingAgent
                    ]);
                    return response()->json(['status' => 'invalid_agent_data'], 400);
                }

                // Atribuir agente à conversa
                $this->chatwootServices->assignAgentToConversation($accountId, $apiToken, $conversationId, $matchingAgent['agent_id']);

                // Abrir conversa se necessário
                if ($payload['status'] !== 'open') {
                    $this->chatwootServices->toggleConversationStatus($accountId, $apiToken, $conversationId);
                }

                // Armazenar dados da conversa na tabela pivot
                $this->storeConversationData($lead->id, $conversationId, $accountId, $matchingAgent['agent_id'], $content, $messageId);

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

            if ($content && $messageId) {
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
