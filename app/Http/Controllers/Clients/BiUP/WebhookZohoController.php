<?php

namespace App\Http\Controllers\Clients\BiUP;

use App\Models\CadenceMessage;
use App\Models\Cadencias;
use App\Models\SyncFlowLeads;
use App\Models\User;
use App\Models\Etapas;
use App\Models\Evolution;
use App\Models\ChatwootConversation;
use App\Models\ChatwootsAgents;
use App\Models\SystemNotification;
use App\Services\ChatwootService;
use App\Services\ZohoCrmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Services\PhoneNumberService;
use App\Services\WebhookLogService;

class WebhookZohoController extends Controller
{
    protected $chatwootService;
    protected $zohoCrmService;
    protected $phoneNumberService;
    protected $webhookLogService;

    public function __construct(
        ChatwootService $chatwootService,
        ZohoCrmService $zohoCrmService,
        PhoneNumberService $phoneNumberService,
        WebhookLogService $webhookLogService
    ) {
        $this->chatwootService = $chatwootService;
        $this->zohoCrmService = $zohoCrmService;
        $this->phoneNumberService = $phoneNumberService;
        $this->webhookLogService = $webhookLogService;
    }

    public function createFromWebhook(Request $request)
    {
        $chatwootAccountId = $request->chatwoot_accoumts ?? null;
        $idCard = $request->id_card ?? 'Não fornecido';
        $user = $chatwootAccountId ? User::where('chatwoot_accoumts', $chatwootAccountId)->first() : null;
        $userId = $user ? $user->id : null;

        // Log inicial com todos os dados relevantes do request
        $this->webhookLogService->info('Webhook request Zoho / Bulkship recebido', [
            'method' => $request->method(),
            'request_data' => [
                'id_card' => $idCard,
                'contact_number' => $request->contact_number,
                'contact_name' => $request->contact_name,
                'contact_email' => $request->contact_email,
                'id_vendedor' => $request->id_vendedor,
                'cadencia_id' => $request->cadencia_id,
                'estagio' => $request->estagio,
                'situacao_contato' => $request->situacao_contato,
                'chatwoot_accoumts' => $chatwootAccountId,
            ],
        ], $chatwootAccountId, $userId, 'zoho');

        // Validação inicial do request
        if (!$request->isMethod('post') || !$request->getContent()) {
            $this->webhookLogService->error('Nenhum dado recebido no webhook', [
                'method' => $request->method(),
                'request_data' => $request->all(),
            ], $chatwootAccountId, $userId, 'zoho');
            return response('No data received', 400);
        }

        // Formatar número de contato
        $contactNumber = $this->phoneNumberService->formatPhoneNumber($request->contact_number);

        if ($contactNumber === 'Não fornecido' && !empty($request->contact_number)) {
            $this->webhookLogService->warning('Falha ao formatar número do lead', [
                'id_card' => $idCard,
                'contact_number' => $request->contact_number,
                'contact_name' => $request->contact_name,
            ], $chatwootAccountId, $userId, 'zoho');

            if ($user) {
                SystemNotification::create([
                    'user_id' => $user->id,
                    'title' => 'Falha ao Formatar Número do Lead',
                    'message' => "Não foi possível formatar o número do lead com ID Card: {$idCard}. Nome do Lead: {$request->contact_name}. Número fornecido: {$request->contact_number}.",
                    'read' => false,
                ]);
                $this->webhookLogService->info('Notificação de falha no formato do número enviada ao usuário', [
                    'user_id' => $user->id,
                    'id_card' => $idCard,
                ], $chatwootAccountId, $userId, 'zoho');
            } else {
                $this->webhookLogService->warning("Nenhum usuário encontrado para chatwoot_accoumts: {$chatwootAccountId}", [
                    'id_card' => $idCard,
                    'contact_number' => $request->contact_number,
                ], $chatwootAccountId, null, 'zoho');
            }
        }

        // Buscar informações do vendedor
        $emailVendedor = 'Não fornecido';
        $nomeVendedor = 'Não fornecido';
        if ($request->id_vendedor && $request->id_vendedor !== 'Não fornecido') {
            $vendedorInfo = $this->zohoCrmService->getUserEmailById($request->id_vendedor);
            if ($vendedorInfo) {
                $emailVendedor = $vendedorInfo['email'] ?? 'Não fornecido';
                $nomeVendedor = $vendedorInfo['name'] ?? 'Não fornecido';
                $this->webhookLogService->info('Informações do vendedor obtidas com sucesso', [
                    'id_vendedor' => $request->id_vendedor,
                    'email_vendedor' => $emailVendedor,
                    'nome_vendedor' => $nomeVendedor,
                ], $chatwootAccountId, $userId, 'zoho');
            } else {
                $this->webhookLogService->warning('Falha ao obter informações do vendedor', [
                    'id_vendedor' => $request->id_vendedor,
                ], $chatwootAccountId, $userId, 'zoho');
            }
        }

        // Buscar lead no SyncFlowLeads por id_card ou contact_number
        $syncEmp = null;
        if ($idCard !== 'Não fornecido') {
            $syncEmp = SyncFlowLeads::where('id_card', $idCard)->first();
            $this->webhookLogService->info("Busca no SyncFlowLeads por id_card: " . ($syncEmp ? 'Encontrado' : 'Não encontrado'), [
                'id_card' => $idCard,
                'contact_number' => $contactNumber,
                'lead_id' => $syncEmp?->id,
            ], $chatwootAccountId, $userId, 'zoho');
        }
        if (!$syncEmp && $contactNumber !== 'Não fornecido') {
            $syncEmp = SyncFlowLeads::where('contact_number', $contactNumber)->first();
            $this->webhookLogService->info("Busca no SyncFlowLeads por contact_number: " . ($syncEmp ? 'Encontrado' : 'Não encontrado'), [
                'contact_number' => $contactNumber,
                'id_card' => $idCard,
                'lead_id' => $syncEmp?->id,
            ], $chatwootAccountId, $userId, 'zoho');
        }

        $chatwootStatus = 'pending';
        $contactId = $syncEmp ? $syncEmp->contact_id : null;

        // Processar contato no Chatwoot
        if ($contactNumber !== 'Não fornecido' && $chatwootAccountId && $user && $user->token_acess) {
            // Buscar contato no Chatwoot por contact_number
            $contacts = $this->chatwootService->searchContatosApi($contactNumber, $chatwootAccountId, $user->token_acess);
            $this->webhookLogService->info("Busca por contact_number {$contactNumber} no Chatwoot", [
                'contact_number' => $contactNumber,
                'contacts_found' => count($contacts),
                'contacts' => array_map(function ($contact) {
                    return [
                        'id' => $contact['id'] ?? 'N/A',
                        'id_contact' => $contact['id_contact'] ?? 'N/A',
                        'name' => $contact['name'] ?? 'N/A',
                        'phone_number' => $contact['phone_number'] ?? 'N/A',
                        'email' => $contact['email'] ?? 'N/A',
                    ];
                }, $contacts),
            ], $chatwootAccountId, $userId, 'zoho');

            if (is_array($contacts) && !empty($contacts)) {
                $contact = $contacts[0];
                $contactIdForUpdate = $contact['id_contact'] ?? $contact['id'];
                $this->webhookLogService->info("Contato existente encontrado no Chatwoot", [
                    'contact_id' => $contactIdForUpdate,
                    'contact_number' => $contactNumber,
                    'contact_name' => $contact['name'] ?? 'N/A',
                ], $chatwootAccountId, $userId, 'zoho');

                // Atualizar contato existente
                $contactData = $this->chatwootService->updateContact(
                    $chatwootAccountId,
                    $user->token_acess,
                    $contactIdForUpdate,
                    $request->contact_name ?? $contact['name'] ?? 'Não fornecido',
                    $request->contact_email !== 'Não fornecido' ? $request->contact_email : null,
                    $contactNumber
                );

                if ($contactData) {
                    $this->webhookLogService->info("Contato atualizado com sucesso no Chatwoot", [
                        'contact_id' => $contactIdForUpdate,
                        'contact_number' => $contactNumber,
                        'contact_name' => $request->contact_name,
                        'contact_email' => $request->contact_email,
                    ], $chatwootAccountId, $userId, 'zoho');
                    $chatwootStatus = 'success';
                    $contactId = $contactData['contact_id'] ?? $contactIdForUpdate;
                } else {
                    $this->webhookLogService->error("Falha ao atualizar contato no Chatwoot", [
                        'contact_id' => $contactIdForUpdate,
                        'contact_number' => $contactNumber,
                    ], $chatwootAccountId, $userId, 'zoho');
                    $chatwootStatus = 'success'; // Considerar sucesso, pois o contato já existe
                    $contactId = $contactIdForUpdate;
                }

                SystemNotification::create([
                    'user_id' => $user->id,
                    'title' => 'Contato Atualizado no Chatwoot',
                    'message' => "O contato com número {$contactNumber} foi atualizado no Chatwoot. ID: {$contactId}.",
                    'read' => false,
                ]);
                $this->webhookLogService->info("Notificação de atualização de contato enviada", [
                    'contact_id' => $contactId,
                    'user_id' => $user->id,
                ], $chatwootAccountId, $userId, 'zoho');
            } else {
                // Criar novo contato no Chatwoot
                try {
                    $contactData = $this->chatwootService->createContact(
                        $chatwootAccountId,
                        $user->token_acess,
                        $request->contact_name ?? 'Não fornecido',
                        $contactNumber,
                        $request->contact_email !== 'Não fornecido' ? $request->contact_email : null,
                        $contactNumber
                    );

                    if ($contactData) {
                        $this->webhookLogService->info("Novo contato criado no Chatwoot", [
                            'contact_id' => $contactData['contact_id'],
                            'contact_number' => $contactNumber,
                            'contact_name' => $request->contact_name,
                            'contact_email' => $request->contact_email,
                        ], $chatwootAccountId, $userId, 'zoho');
                        $chatwootStatus = 'success';
                        $contactId = $contactData['contact_id'];
                    } else {
                        throw new \Exception("Resposta vazia ao criar contato no Chatwoot");
                    }
                } catch (\Exception $e) {
                    $this->webhookLogService->error("Erro ao criar contato no Chatwoot: {$e->getMessage()}", [
                        'contact_number' => $contactNumber,
                        'contact_name' => $request->contact_name,
                        'exception' => [
                            'message' => $e->getMessage(),
                            'code' => $e->getCode(),
                        ],
                    ], $chatwootAccountId, $userId, 'zoho');

                    if (strpos($e->getMessage(), '422') !== false && strpos($e->getMessage(), 'has already been taken') !== false) {
                        $contacts = $this->chatwootService->searchContatosApi($contactNumber, $chatwootAccountId, $user->token_acess);
                        $this->webhookLogService->info("Tentativa de recuperação de contato existente", [
                            'contact_number' => $contactNumber,
                            'contacts_found' => count($contacts),
                        ], $chatwootAccountId, $userId, 'zoho');

                        if (is_array($contacts) && !empty($contacts)) {
                            $contact = $contacts[0];
                            $contactId = $contact['id_contact'] ?? $contact['id'];
                            $this->webhookLogService->info("Contato recuperado com sucesso", [
                                'contact_id' => $contactId,
                                'contact_number' => $contactNumber,
                            ], $chatwootAccountId, $userId, 'zoho');
                            $chatwootStatus = 'success';
                        } else {
                            $this->webhookLogService->error("Falha ao recuperar contato existente", [
                                'contact_number' => $contactNumber,
                            ], $chatwootAccountId, $userId, 'zoho');
                            $chatwootStatus = 'failed';
                        }
                    } else {
                        $chatwootStatus = 'failed';
                    }
                }
            }
        } else {
            $this->webhookLogService->error("Não foi possível processar contato no Chatwoot", [
                'contact_number' => $contactNumber,
                'chatwoot_accoumts' => $chatwootAccountId,
                'user_exists' => !empty($user),
                'token_exists' => !empty($user->token_acess ?? null),
            ], $chatwootAccountId, $userId, 'zoho');
            $chatwootStatus = 'failed';
        }

        // Salvar ou atualizar lead no SyncFlowLeads
        if ($syncEmp) {
            $oldEstagio = $syncEmp->estagio;
            $syncEmp->contact_name = $request->contact_name ?? $syncEmp->contact_name;
            $syncEmp->contact_number = $contactNumber;
            $syncEmp->contact_email = $request->contact_email ?? $syncEmp->contact_email;
            $syncEmp->estagio = $request->estagio ?? $syncEmp->estagio;
            $syncEmp->chatwoot_accoumts = $chatwootAccountId ?? $syncEmp->chatwoot_accoumts;
            $syncEmp->situacao_contato = $request->situacao_contato ?? $syncEmp->situacao_contato;
            $syncEmp->email_vendedor = $emailVendedor;
            $syncEmp->nome_vendedor = $nomeVendedor;
            $syncEmp->id_vendedor = $request->id_vendedor ?? $syncEmp->id_vendedor;
            $syncEmp->chatwoot_status = $chatwootStatus;
            $syncEmp->contact_id = $contactId;
            $syncEmp->identifier = $contactNumber; // Sempre usar contact_number como identifier
            $syncEmp->updated_at = now();

            if ($request->cadencia_id) {
                $cadencia = Cadencias::find($request->cadencia_id);
                if ($cadencia) {
                    $syncEmp->cadencia_id = $request->cadencia_id;
                    $this->webhookLogService->info("Cadência atribuída ao lead existente", [
                        'lead_id' => $syncEmp->id,
                        'cadencia_id' => $request->cadencia_id,
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                } else {
                    $this->webhookLogService->warning("Cadência ID {$request->cadencia_id} não encontrada", [
                        'id_card' => $idCard,
                        'lead_id' => $syncEmp->id,
                    ], $chatwootAccountId, $userId, 'zoho');
                }
            }

            try {
                $syncEmp->save();
                $this->webhookLogService->info("Lead atualizado com sucesso", [
                    'lead_id' => $syncEmp->id,
                    'id_card' => $idCard,
                    'chatwoot_status' => $chatwootStatus,
                    'contact_id' => $contactId,
                    'contact_number' => $contactNumber,
                ], $chatwootAccountId, $userId, 'zoho');
            } catch (\Exception $e) {
                $this->webhookLogService->error("Erro ao atualizar lead no SyncFlowLeads: {$e->getMessage()}", [
                    'lead_id' => $syncEmp->id,
                    'id_card' => $idCard,
                    'exception' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ],
                ], $chatwootAccountId, $userId, 'zoho');
            }
        } else {
            $syncEmp = new SyncFlowLeads();
            $syncEmp->id_card = $idCard;
            $syncEmp->contact_name = $request->contact_name ?? 'Não fornecido';
            $syncEmp->contact_number = $contactNumber;
            $syncEmp->contact_email = $request->contact_email ?? 'Não fornecido';
            $syncEmp->estagio = $request->estagio ?? 'Não fornecido';
            $syncEmp->chatwoot_accoumts = $chatwootAccountId ?? null;
            $syncEmp->situacao_contato = $request->situacao_contato ?? 'Não fornecido';
            $syncEmp->email_vendedor = $emailVendedor;
            $syncEmp->nome_vendedor = $nomeVendedor;
            $syncEmp->id_vendedor = $request->id_vendedor ?? 'Não fornecido';
            $syncEmp->chatwoot_status = $chatwootStatus;
            $syncEmp->contact_id = $contactId;
            $syncEmp->identifier = $contactNumber; // Sempre usar contact_number como identifier
            $syncEmp->completed_cadences = '0';
            $syncEmp->created_at = now();

            if ($request->cadencia_id) {
                $cadencia = Cadencias::find($request->cadencia_id);
                if ($cadencia) {
                    $syncEmp->cadencia_id = $request->cadencia_id;
                    $this->webhookLogService->info("Cadência atribuída ao novo lead", [
                        'cadencia_id' => $request->cadencia_id,
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                } else {
                    $this->webhookLogService->warning("Cadência ID {$request->cadencia_id} não encontrada", [
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                }
            }

            try {
                $syncEmp->save();
                $this->webhookLogService->info("Novo lead criado com sucesso", [
                    'lead_id' => $syncEmp->id,
                    'id_card' => $idCard,
                    'chatwoot_status' => $chatwootStatus,
                    'contact_id' => $contactId,
                    'contact_number' => $contactNumber,
                ], $chatwootAccountId, $userId, 'zoho');
            } catch (\Exception $e) {
                $this->webhookLogService->error("Erro ao criar lead no SyncFlowLeads: {$e->getMessage()}", [
                    'id_card' => $idCard,
                    'exception' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ],
                ], $chatwootAccountId, $userId, 'zoho');
            }

            // Atualizar status no Zoho
            try {
                $leadExists = $this->zohoCrmService->checkLeadExists($idCard);
                if ($leadExists) {
                    $response = $this->zohoCrmService->updateLeadStatusWhatsApp($idCard, 'Não respondido');
                    if ($response && isset($response['status']) && $response['status'] === 'success') {
                        $this->webhookLogService->info("Status WhatsApp atualizado para 'Não respondido'", [
                            'lead_id' => $syncEmp->id,
                            'id_card' => $idCard,
                        ], $chatwootAccountId, $userId, 'zoho');
                    } else {
                        $this->webhookLogService->error("Falha ao atualizar Status_WhatsApp", [
                            'id_card' => $idCard,
                            'response' => $response,
                        ], $chatwootAccountId, $userId, 'zoho');
                    }
                } else {
                    $this->webhookLogService->error("Lead não encontrado no Zoho para atualização de Status_WhatsApp", [
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                }
            } catch (\Exception $e) {
                $this->webhookLogService->error("Erro ao atualizar Status_WhatsApp: {$e->getMessage()}", [
                    'id_card' => $idCard,
                    'exception' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ],
                ], $chatwootAccountId, $userId, 'zoho');
            }
        }

        // Atribuir agente à conversa no Chatwoot
        if ($syncEmp && $contactNumber !== 'Não fornecido' && $syncEmp->cadencia_id) {
            try {
                $cadencia = Cadencias::find($syncEmp->cadencia_id);
                if (!$cadencia) {
                    $this->webhookLogService->error("Cadência não encontrada", [
                        'lead_id' => $syncEmp->id,
                        'cadencia_id' => $syncEmp->cadencia_id,
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                    return response('Webhook received successfully', 200);
                }

                $evolution = Evolution::find($cadencia->evolution_id);
                if (!$evolution || !$evolution->api_post || !$evolution->apikey) {
                    $this->webhookLogService->error("Caixa Evolution ou credenciais não encontradas", [
                        'lead_id' => $syncEmp->id,
                        'evolution_id' => $cadencia->evolution_id,
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                    return response('Webhook received successfully', 200);
                }

                $conversation = ChatwootConversation::where('sync_flow_lead_id', $syncEmp->id)
                    ->where('status', 'open')
                    ->first();

                if ($conversation) {
                    $chatWootAgent = ChatwootsAgents::where('email', $syncEmp->email_vendedor)
                        ->where('chatwoot_account_id', $chatwootAccountId)
                        ->first();

                    if (!$chatWootAgent && $emailVendedor !== 'Não fornecido') {
                        $chatWootAgent = ChatwootsAgents::where('email', $emailVendedor)
                            ->where('chatwoot_account_id', $chatwootAccountId)
                            ->first();
                        $this->webhookLogService->info("Busca de agente por email_vendedor: " . ($chatWootAgent ? 'Encontrado' : 'Não encontrado'), [
                            'email_vendedor' => $emailVendedor,
                            'chatwoot_account_id' => $chatwootAccountId,
                        ], $chatwootAccountId, $userId, 'zoho');
                    }

                    if ($chatWootAgent && $chatWootAgent->agent_id) {
                        $apiToken = $user->token_acess;
                        $this->chatwootService->assignAgentToConversation(
                            $chatwootAccountId,
                            $apiToken,
                            $conversation->conversation_id,
                            $chatWootAgent->agent_id
                        );

                        $this->webhookLogService->info("Agente atribuído à conversa", [
                            'conversation_id' => $conversation->conversation_id,
                            'agent_id' => $chatWootAgent->agent_id,
                            'lead_id' => $syncEmp->id,
                            'id_card' => $idCard,
                            'evolution_id' => $cadencia->evolution_id,
                        ], $chatwootAccountId, $userId, 'zoho');

                        $conversation->agent_assigned_once = true;
                        $conversation->agent_id = $chatWootAgent->agent_id;
                        $conversation->save();
                    } else {
                        $this->webhookLogService->warning("Agente não encontrado para atribuição", [
                            'email_vendedor' => $syncEmp->email_vendedor,
                            'conversation_id' => $conversation->conversation_id,
                            'lead_id' => $syncEmp->id,
                            'id_card' => $idCard,
                        ], $chatwootAccountId, $userId, 'zoho');
                    }
                } else {
                    $this->webhookLogService->info("Nenhuma conversa aberta encontrada", [
                        'lead_id' => $syncEmp->id,
                        'id_card' => $idCard,
                        'contact_number' => $contactNumber,
                    ], $chatwootAccountId, $userId, 'zoho');
                }
            } catch (\Exception $e) {
                $this->webhookLogService->error("Erro ao atribuir agente à conversa: {$e->getMessage()}", [
                    'lead_id' => $syncEmp->id,
                    'id_card' => $idCard,
                    'exception' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ],
                ], $chatwootAccountId, $userId, 'zoho');
            }
        } else {
            $this->webhookLogService->info("Atribuição de agente ignorada: número inválido ou cadência não atribuída", [
                'id_card' => $idCard,
                'contact_number' => $contactNumber,
                'cadencia_id' => $syncEmp->cadencia_id ?? 'N/A',
                'lead_id' => $syncEmp->id ?? 'N/A',
            ], $chatwootAccountId, $userId, 'zoho');
        }

        return response('Webhook received successfully', 200);
    }
}