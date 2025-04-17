<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\SyncFlowLeads;
use App\Models\ChatwootConversation;
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

            // Verifica se é um evento de atualização de conversa
            if (isset($payload['event']) && $payload['event'] === 'conversation_updated') {
                $conversationId = $payload['id'];
                $contactEmail = $payload['meta']['sender']['email'] ?? null;
                $contactPhone = $payload['meta']['sender']['phone_number'] ?? null;

                Log::info('Processando atualização da conversa', [
                    'conversation_id' => $conversationId,
                    'email' => $contactEmail,
                    'phone' => $contactPhone
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
                }else {
                    Log::info('Lead encontrado', [
                        'id_do_lead' => $lead->id,
                        'email' => $contactEmail,
                        'telefone' => $contactPhone
                    ]);
                }

                // Encontrar o usuário associado ao email_vendedor para obter detalhes da conta Chatwoot
                $user = User::where('email', $lead->email_vendedor)->first();

                if (!$user || !$user->chatwoot_accoumts || !$user->token_acess) {
                    Log::warning('Detalhes do usuário ou conta Chatwoot não encontrados', [
                        'email_vendedor' => $lead->email_vendedor,
                        'conversation_id' => $conversationId
                    ]);
                    return response()->json(['status' => 'user_or_chatwoot_details_not_found'], 404);
                }

                $accountId = $user->chatwoot_accoumts;
                $apiToken = $user->token_acess;

                // Obter lista de agentes
                $agents = $this->chatwootServices->getAgents($accountId, $apiToken);

                Log::info('Lista de agentes obtida', [
                    'account_id' => $accountId,
                    'agents_count' => count($agents['email'])
                ]);

                // Encontrar agente com email_vendedor correspondente
                $matchingAgent = collect($agents['email'])->firstWhere('email', $lead->email_vendedor);

                if (!$matchingAgent) {
                    Log::info('Nenhum agente correspondente encontrado', [
                        'email_vendedor' => $lead->email_vendedor,
                        'conversation_id' => $conversationId
                    ]);
                    return response()->json(['status' => 'agent_not_found'], 404);
                }

                // Atribuir agente à conversa
                $this->chatwootServices->assignAgentToConversation($accountId, $apiToken, $conversationId, $matchingAgent['id']);

                // Abrir conversa se necessário
                if ($payload['status'] !== 'open') {
                    $this->chatwootServices->toggleConversationStatus($accountId, $apiToken, $conversationId);
                }

                // Armazenar dados da conversa na tabela pivot
                $this->storeConversationData($lead->id, $conversationId, $accountId, $matchingAgent['id']);

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

    private function storeConversationData($leadId, $conversationId, $accountId, $agentId)
    {
        try {
            ChatwootConversation::updateOrCreate(
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

            Log::info('Conversa armazenada com sucesso', [
                'sync_flow_lead_id' => $leadId,
                'conversation_id' => $conversationId
            ]);
        } catch (\Exception $exception) {
            Log::error('Falha ao armazenar conversa', [
                'sync_flow_lead_id' => $leadId,
                'conversation_id' => $conversationId,
                'error' => $exception->getMessage()
            ]);
        }
    }
}
