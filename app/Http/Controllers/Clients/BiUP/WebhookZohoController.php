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

        // Log inicial do webhook
        $this->webhookLogService->info('Webhook request Zoho / Bulkship', [
            'method' => $request->method(),
            'content' => $request->all(),
        ], $chatwootAccountId, $userId, 'zoho');

        if (!$request->isMethod('post') || !$request->getContent()) {
            $this->webhookLogService->error('Nenhum dado recebido no webhook', [
                'method' => $request->method(),
                'content' => $request->all(),
            ], $chatwootAccountId, $userId, 'zoho');
            return response('No data received', 400);
        }

        $contactNumber = $this->phoneNumberService->formatPhoneNumber($request->contact_number);

        if ($contactNumber === 'Não fornecido' && !empty($request->contact_number)) {
            if ($user) {
                SystemNotification::create([
                    'user_id' => $user->id,
                    'title' => 'Falha ao Formatar Número do Lead',
                    'message' => "Não foi possível formatar o número do lead com ID Card: {$idCard}. Nome do Lead: {$request->contact_name}. Número fornecido: {$request->contact_number}.",
                    'read' => false
                ]);

                $this->webhookLogService->warning('Falha ao formatar número do lead', [
                    'id_card' => $idCard,
                    'contact_number' => $request->contact_number,
                    'contact_name' => $request->contact_name,
                ], $chatwootAccountId, $user->id, 'zoho');
            } else {
                $this->webhookLogService->warning("Nenhum usuário encontrado para chatwoot_accoumts: {$chatwootAccountId}. Notificação de falha na formatação não enviada.", [
                    'chatwoot_accoumts' => $chatwootAccountId,
                    'id_card' => $idCard,
                    'contact_number' => $request->contact_number,
                    'contact_name' => $request->contact_name,
                ], $chatwootAccountId, null, 'zoho');
            }
        }

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
            $this->webhookLogService->info("Busca no SyncFlowLeads por id_card {$idCard}: " . ($syncEmp ? 'Encontrado' : 'Não encontrado'), [
                'id_card' => $idCard,
                'contact_number' => $contactNumber,
            ], $chatwootAccountId, $userId, 'zoho');
        }
        if (!$syncEmp && $contactNumber !== 'Não fornecido') {
            $syncEmp = SyncFlowLeads::where('contact_number', $contactNumber)->first();
            $this->webhookLogService->info("Busca no SyncFlowLeads por contact_number {$contactNumber}: " . ($syncEmp ? 'Encontrado' : 'Não encontrado'), [
                'contact_number' => $contactNumber,
                'id_card' => $idCard,
            ], $chatwootAccountId, $userId, 'zoho');
        }

        $chatwootStatus = 'pending';
        $contactId = $syncEmp ? $syncEmp->contact_id : null;
        $identifier = $syncEmp ? $syncEmp->identifier : null;

        if ($contactNumber !== 'Não fornecido' && $chatwootAccountId) {
            if ($user && $user->token_acess) {
                // Gerar o identifier apenas se não existir
                if (!$identifier && $syncEmp && $syncEmp->id) {
                    $identifier = $contactNumber;
                    $this->webhookLogService->info("Identifier gerado: {$identifier} para lead ID {$syncEmp->id}", [
                        'id_card' => $idCard,
                        'sync_emp_id' => $syncEmp->id,
                        'contact_number' => $contactNumber,
                    ], $chatwootAccountId, $userId, 'zoho');
                } elseif (!$identifier && $idCard !== 'Não fornecido') {
                    $identifier = $contactNumber;
                    $this->webhookLogService->info("Identifier gerado (fallback): {$identifier} para id_card {$idCard}", [
                        'id_card' => $idCard,
                        'contact_number' => $contactNumber,
                    ], $chatwootAccountId, $userId, 'zoho');
                }

                // Buscar contato no Chatwoot
                $contacts = [];
                if ($identifier) {
                    $this->webhookLogService->info("Buscando contato no Chatwoot por identifier: {$identifier}", [
                        'chatwoot_accoumts' => $chatwootAccountId,
                    ], $chatwootAccountId, $userId, 'zoho');
                    $contacts = $this->chatwootService->searchContatosApi($identifier, $chatwootAccountId, $user->token_acess);
                    $this->webhookLogService->info("Resultado da busca por identifier {$identifier}", [
                        'contacts' => $contacts,
                    ], $chatwootAccountId, $userId, 'zoho');
                }
                if (empty($contacts) && $contactNumber !== 'Não fornecido') {
                    $this->webhookLogService->info("Buscando contato no Chatwoot por phone_number: {$contactNumber}", [
                        'chatwoot_accoumts' => $chatwootAccountId,
                    ], $chatwootAccountId, $userId, 'zoho');
                    $contacts = $this->chatwootService->searchContatosApi($contactNumber, $chatwootAccountId, $user->token_acess);
                    $this->webhookLogService->info("Resultado da busca por phone_number {$contactNumber}", [
                        'contacts' => $contacts,
                    ], $chatwootAccountId, $userId, 'zoho');
                }

                $logContacts = is_array($contacts) ? array_map(function ($contact) {
                    return [
                        'id' => $contact['id'] ?? 'N/A',
                        'id_contact' => $contact['id_contact'] ?? 'N/A',
                        'name' => $contact['name'] ?? 'N/A',
                        'phone_number' => $contact['phone_number'] ?? 'N/A',
                        'email' => $contact['email'] ?? 'N/A',
                        'identifier' => $contact['identifier'] ?? 'N/A'
                    ];
                }, $contacts) : [];
                $this->webhookLogService->info("Busca por {$contactNumber} ou identifier {$identifier} retornou " . count($contacts) . " contatos", [
                    'contacts' => $logContacts,
                ], $chatwootAccountId, $userId, 'zoho');

                if (is_array($contacts) && !empty($contacts)) {
                    $contact = $contacts[0];
                    $this->webhookLogService->info("Contato selecionado para atualização", [
                        'contact' => $contact,
                    ], $chatwootAccountId, $userId, 'zoho');

                    SystemNotification::create([
                        'user_id' => $user->id,
                        'title' => 'Contato já existe no Chatwoot',
                        'message' => "O contato com o número {$contactNumber} já existe no Chatwoot. Contato Atualizado!",
                        'read' => false
                    ]);
                    $this->webhookLogService->info("Notificação enviada para o usuário sobre contato existente no Chatwoot", [
                        'user_id' => $user->id,
                    ], $chatwootAccountId, $userId, 'zoho');

                    $contactIdForUpdate = isset($contact['id_contact']) ? $contact['id_contact'] : $contact['id'];
                    $contactData = $this->chatwootService->updateContact(
                        $chatwootAccountId,
                        $user->token_acess,
                        $contactIdForUpdate,
                        $request->contact_name ?? $contact['name'] ?? 'Não fornecido',
                        $request->contact_email !== 'Não fornecido' ? $request->contact_email : null,
                        $identifier ?? $contactNumber
                    );
                    if ($contactData) {
                        $this->webhookLogService->info("Contato atualizado no Chatwoot: ID {$contactIdForUpdate}", [
                            'contact_id' => $contactIdForUpdate,
                            'contact_number' => $contactNumber,
                        ], $chatwootAccountId, $userId, 'zoho');
                        $chatwootStatus = 'success';
                        $contactId = $contactData['contact_id'] ?? $contactIdForUpdate;
                        $identifier = $identifier ?? $contactNumber;
                    } else {
                        $this->webhookLogService->error("Falha ao atualizar contato no Chatwoot: ID {$contactIdForUpdate}", [
                            'contact_id' => $contactIdForUpdate,
                            'contact_number' => $contactNumber,
                        ], $chatwootAccountId, $userId, 'zoho');
                        $chatwootStatus = 'success'; // Considerar sucesso, pois o contato já existe
                        $contactId = $contactIdForUpdate;
                    }
                } else {
                    try {
                        $contactData = $this->chatwootService->createContact(
                            $chatwootAccountId,
                            $user->token_acess,
                            $request->contact_name ?? 'Não fornecido',
                            $contactNumber,
                            $request->contact_email !== 'Não fornecido' ? $request->contact_email : null,
                            $identifier
                        );
                        if ($contactData) {
                            $this->webhookLogService->info("Contato criado no Chatwoot: contact_id {$contactData['contact_id']}", [
                                'contact_id' => $contactData['contact_id'],
                                'contact_number' => $contactNumber,
                                'identifier' => $identifier,
                            ], $chatwootAccountId, $userId, 'zoho');
                            $chatwootStatus = 'success';
                            $contactId = $contactData['contact_id'];
                            $identifier = $contactData['identifier'] ?? $identifier;
                        } else {
                            throw new \Exception("Resposta vazia ao criar contato no Chatwoot");
                        }
                    } catch (\Exception $e) {
                        $this->webhookLogService->error("Erro ao criar contato no Chatwoot: {$e->getMessage()}", [
                            'contact_number' => $contactNumber,
                            'exception' => [
                                'message' => $e->getMessage(),
                                'code' => $e->getCode(),
                            ],
                        ], $chatwootAccountId, $userId, 'zoho');

                        if (strpos($e->getMessage(), '422') !== false && strpos($e->getMessage(), 'has already been taken') !== false) {
                            $this->webhookLogService->info("Contato já existe no Chatwoot. Tentando recuperar contato existente", [
                                'contact_number' => $contactNumber,
                            ], $chatwootAccountId, $userId, 'zoho');
                            $contacts = $this->chatwootService->searchContatosApi($contactNumber, $chatwootAccountId, $user->token_acess);
                            $this->webhookLogService->info("Resultado da busca de recuperação por phone_number {$contactNumber}", [
                                'contacts' => $contacts,
                            ], $chatwootAccountId, $userId, 'zoho');
                            if (is_array($contacts) && !empty($contacts)) {
                                $contact = $contacts[0];
                                $chatwootStatus = 'success';
                                $contactId = isset($contact['id_contact']) ? $contact['id_contact'] : $contact['id'];
                                $identifier = $contact['identifier'] ?? $identifier;
                                $this->webhookLogService->info("Contato recuperado com sucesso no Chatwoot: contact_id {$contactId}", [
                                    'contact_id' => $contactId,
                                    'contact_number' => $contactNumber,
                                ], $chatwootAccountId, $userId, 'zoho');
                            } else {
                                $this->webhookLogService->error("Falha ao recuperar contato existente no Chatwoot", [
                                    'contact_number' => $contactNumber,
                                ], $chatwootAccountId, $userId, 'zoho');
                                $chatwootStatus = 'failed';
                            }
                        } else {
                            $this->webhookLogService->error("Falha ao criar contato no Chatwoot: {$e->getMessage()}", [
                                'contact_number' => $contactNumber,
                            ], $chatwootAccountId, $userId, 'zoho');
                            $chatwootStatus = 'failed';
                        }
                    }
                }
            } else {
                $this->webhookLogService->error("Usuário ou token não encontrado para chatwoot_accoumts: {$chatwootAccountId}", [
                    'id_card' => $idCard,
                ], $chatwootAccountId, $userId, 'zoho');
                $chatwootStatus = 'failed';
            }
        } else {
            $this->webhookLogService->info("Número inválido ou chatwoot_accoumts não fornecido: {$contactNumber}", [
                'id_card' => $idCard,
                'chatwoot_accoumts' => $chatwootAccountId,
            ],$chatwootAccountId, $userId, 'zoho');
            $chatwootStatus = 'skipped';
        }

        // Salvar ou atualizar o lead no SyncFlowLeads
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
            $syncEmp->identifier = $contactNumber;
            $syncEmp->updated_at = now();

            if ($request->cadencia_id) {
                $cadencia = Cadencias::find($request->cadencia_id);
                if ($cadencia) {
                    $syncEmp->cadencia_id = $request->cadencia_id;
                    $this->webhookLogService->info("Cadência ID {$request->cadencia_id} atribuída diretamente ao lead ID {$syncEmp->id}", [
                        'id_card' => $idCard,
                        'lead_id' => $syncEmp->id,
                    ], $chatwootAccountId, $userId, 'zoho');
                } else {
                    $this->webhookLogService->warning("Cadência ID {$request->cadencia_id} não encontrada no banco de dados. Cadência não atribuída.", [
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                }
            }

            try {
                $syncEmp->save();
                $this->webhookLogService->info("Lead existente atualizado com ID: {$syncEmp->id}, chatwoot_status: {$chatwootStatus}, contact_id: {$contactId}, identifier: {$identifier}", [
                    'lead_id' => $syncEmp->id,
                    'id_card' => $idCard,
                ], $chatwootAccountId, $userId, 'zoho');
            } catch (\Exception $e) {
                $this->webhookLogService->error("Erro ao salvar lead existente no SyncFlowLeads: {$e->getMessage()}", [
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
            $syncEmp->identifier = $contactNumber;
            $syncEmp->completed_cadences = '0';
            $syncEmp->created_at = now();

            if ($request->cadencia_id) {
                $cadencia = Cadencias::find($request->cadencia_id);
                if ($cadencia) {
                    $syncEmp->cadencia_id = $request->cadencia_id;
                    $this->webhookLogService->info("Cadência ID {$request->cadencia_id} atribuída ao novo lead", [
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                } else {
                    $this->webhookLogService->warning("Cadência ID {$request->cadencia_id} não encontrada no banco de dados. Cadência não atribuída.", [
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                }
            } else {
                $this->webhookLogService->info("Nenhum cadência_id recebido; cadência não atribuída ao novo lead", [
                    'id_card' => $idCard,
                ], $chatwootAccountId, $userId, 'zoho');
            }

            try {
                $syncEmp->save();
                $this->webhookLogService->info("Novo lead salvo com ID: {$syncEmp->id}, chatwoot_status: {$chatwootStatus}, contact_id: {$contactId}, identifier: {$identifier}", [
                    'lead_id' => $syncEmp->id,
                    'id_card' => $idCard,
                ], $chatwootAccountId, $userId, 'zoho');
            } catch (\Exception $e) {
                $this->webhookLogService->error("Erro ao salvar novo lead no SyncFlowLeads: {$e->getMessage()}", [
                    'id_card' => $idCard,
                    'exception' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ],
                ], $chatwootAccountId, $userId, 'zoho');
            }

            try {
                $leadExists = $this->zohoCrmService->checkLeadExists($idCard);
                if ($leadExists) {
                    $response = $this->zohoCrmService->updateLeadStatusWhatsApp($idCard, 'Não respondido');
                    if ($response && isset($response['status']) && $response['status'] === 'success') {
                        $this->webhookLogService->info("Status WhatsApp atualizado para 'Não respondido' para lead ID {$syncEmp->id}", [
                            'lead_id' => $syncEmp->id,
                            'id_card' => $idCard,
                        ], $chatwootAccountId, $userId, 'zoho');
                    } else {
                        $this->webhookLogService->error("Falha ao atualizar Status_WhatsApp para lead ID {$idCard}", [
                            'response' => $response,
                        ], $chatwootAccountId, $userId, 'zoho');
                    }
                } else {
                    $this->webhookLogService->error("Lead não encontrado no Zoho para ID {$idCard}. Atualização de Status_WhatsApp ignorada.", [
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                }
            } catch (\Exception $e) {
                $this->webhookLogService->error("Erro ao atualizar Status_WhatsApp para lead ID {$idCard}: {$e->getMessage()}", [
                    'id_card' => $idCard,
                    'exception' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ],
                ], $chatwootAccountId, $userId, 'zoho');
            }
        }

        if ($syncEmp && $contactNumber !== 'Não fornecido' && $syncEmp->cadencia_id) {
            try {
                $cadencia = Cadencias::find($syncEmp->cadencia_id);
                if (!$cadencia) {
                    $this->webhookLogService->error("Cadência não encontrada para o lead", [
                        'lead_id' => $syncEmp->id,
                        'cadencia_id' => $syncEmp->cadencia_id,
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                    return response('Webhook received successfully', 200);
                }

                $evolution = Evolution::find($cadencia->evolution_id);
                if (!$evolution || !$evolution->api_post || !$evolution->apikey) {
                    $this->webhookLogService->error("Caixa Evolution ou credenciais não encontradas para evolution_id: {$cadencia->evolution_id}", [
                        'lead_id' => $syncEmp->id,
                        'id_card' => $idCard,
                    ], $chatwootAccountId, $userId, 'zoho');
                    return response('Webhook received successfully', 200);
                }

                $conversation = ChatwootConversation::where('sync_flow_lead_id', $syncEmp->id)
                    ->where('status', 'open')
                    ->first();

                if ($conversation) {
                    $this->webhookLogService->info("Dados usados para buscar agente", [
                        'email_vendedor' => $syncEmp->email_vendedor,
                        'chatwoot_account_id' => $chatwootAccountId,
                    ], $chatwootAccountId, $userId, 'zoho');

                    $chatWootAgent = ChatwootsAgents::where('email', $syncEmp->email_vendedor)
                        ->where('chatwoot_account_id', $chatwootAccountId)
                        ->first();

                    if (!$chatWootAgent && $emailVendedor && $emailVendedor !== 'Não fornecido') {
                        $this->webhookLogService->info("Tentativa 2: buscando agente pelo email do vendedor: {$emailVendedor}", [], $idCard, $chatwootAccountId, $userId);
                        $chatWootAgent = ChatwootsAgents::where('email', $emailVendedor)
                            ->where('chatwoot_account_id', $chatwootAccountId)
                            ->first();
                    }

                    $this->webhookLogService->info("Agente encontrado: " . ($chatWootAgent ? 'Sim' : 'Não'), [
                        'email_vendedor' => $syncEmp->email_vendedor,
                        'chatwoot_account_id' => $chatwootAccountId,
                        'agent_id' => $chatWootAgent->agent_id ?? 'N/A',
                    ], $chatwootAccountId, $userId, 'zoho');

                    if ($chatWootAgent && $chatWootAgent->agent_id) {
                        $this->webhookLogService->info("Dados para atribuição de agente", [
                            'evolution_id' => $cadencia->evolution_id,
                            'api_post' => $evolution->api_post,
                            'apikey' => $evolution->apikey,
                            'conversation_id' => $conversation->conversation_id,
                            'agent_id' => $chatWootAgent->agent_id,
                        ], $chatwootAccountId, $userId, 'zoho');

                        $apiToken = $user ? $user->token_acess : null;
                        $this->chatwootService->assignAgentToConversation(
                            $chatwootAccountId,
                            $apiToken,
                            $conversation->conversation_id,
                            $chatWootAgent->agent_id
                        );

                        $this->webhookLogService->info('Agente atribuído (ou reatribuído) à conversa pelo webhook Zoho', [
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
                        $this->webhookLogService->warning('Agente Chatwoot não encontrado ou account_id não corresponde', [
                            'email_vendedor' => $syncEmp->email_vendedor,
                            'conversation_id' => $conversation->conversation_id,
                            'lead_id' => $syncEmp->id,
                            'evolution_id' => $cadencia->evolution_id,
                        ], $chatwootAccountId, $userId, 'zoho');
                    }
                } else {
                    $this->webhookLogService->info('Nenhuma conversa aberta encontrada para o lead', [
                        'lead_id' => $syncEmp->id,
                        'id_card' => $idCard,
                        'contact_number' => $contactNumber,
                    ], $chatwootAccountId, $userId, 'zoho');
                }
            } catch (\Exception $e) {
                $this->webhookLogService->error('Erro ao verificar ou atribuir agente à conversa no Chatwoot', [
                    'lead_id' => $syncEmp->id,
                    'id_card' => $idCard,
                    'exception' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ],
                    'evolution_id' => $cadencia->evolution_id ?? 'N/A',
                ], $chatwootAccountId, $userId, 'zoho');
            }
        } else {
            $this->webhookLogService->info('Não foi possível verificar conversas abertas: número inválido ou cadência não atribuída', [
                'id_card' => $idCard,
                'contact_number' => $contactNumber,
                'cadencia_id' => $syncEmp->cadencia_id ?? 'N/A',
            ], $chatwootAccountId, $userId, 'zoho');
        }

        return response('Webhook received successfully', 200);
    }
}
