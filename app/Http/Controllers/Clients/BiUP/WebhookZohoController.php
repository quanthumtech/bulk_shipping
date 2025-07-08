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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;

class WebhookZohoController extends Controller
{
    protected $chatwootService;
    protected $zohoCrmService;

    public function __construct(ChatwootService $chatwootService, ZohoCrmService $zohoCrmService)
    {
        $this->chatwootService = $chatwootService;
        $this->zohoCrmService = $zohoCrmService;
    }

    /**
     * Formata o número de telefone para o padrão +55 (ex: +5512988...).
     *
     * @param string|null $number Número de telefone a ser formatado
     * @return string Número formatado ou 'Não fornecido' se inválido
     */
    protected function formatPhoneNumber($number)
    {
        if (preg_match('/^\+55\d{10,11}$/', $number)) {
            Log::info("Número já está no padrão: {$number}");
            return $number;
        }

        if (empty($number) || $number === 'Não fornecido') {
            Log::info("Número não fornecido ou vazio: {$number}");
            return 'Não fornecido';
        }

        $cleanNumber = preg_replace('/[^0-9]/', '', $number);
        $length = strlen($cleanNumber);

        if (substr($cleanNumber, 0, 1) === '0') {
            $cleanNumber = substr($cleanNumber, 1);
            $length = strlen($cleanNumber);
        }

        $validDDDs = [
            11, 12, 13, 14, 15, 16, 17, 18, 19,
            21, 22, 24, 27, 28,
            31, 32, 33, 34, 35, 37, 38,
            41, 42, 43, 44, 45, 46, 47, 48, 49,
            51, 53, 54, 55,
            61, 62, 63, 64, 65, 66, 67, 68, 69,
            71, 73, 74, 75, 77, 79,
            81, 82, 83, 84, 85, 86, 87, 88, 89,
            91, 92, 93, 94, 95, 96, 97, 98, 99
        ];

        if ($length < 10 || $length > 11) {
            Log::warning("Número inválido, comprimento incorreto: {$number} (limpo: {$cleanNumber}, {$length} dígitos)");
            return 'Não fornecido';
        }

        $ddd = intval(substr($cleanNumber, 0, 2));
        if (!in_array($ddd, $validDDDs)) {
            Log::warning("DDD inválido: {$ddd}");
            return 'Não fornecido';
        }

        $formattedNumber = '+55' . $cleanNumber;
        Log::info("Número formatado com sucesso: {$number} -> {$formattedNumber}");
        return $formattedNumber;
    }

    public function createFromWebhook(Request $request)
    {
        Log::info('Webhook request Zoho / Bulkship', [
            'method' => $request->method(),
            'content' => $request->getContent(),
            'headers' => $request->headers->all(),
            'id_card' => $request->id_card ?? 'Não fornecido'
        ]);

        if (!$request->isMethod('post') || !$request->getContent()) {
            Log::error('Nenhum dado recebido no webhook');
            return response('No data received', 400);
        }

        $idCard = $request->id_card ?? 'Não fornecido';
        $contactNumber = $this->formatPhoneNumber($request->contact_number);
        $contactNumberEmpresa = $this->formatPhoneNumber($request->contact_number_empresa);

        if ($contactNumber === 'Não fornecido' && !empty($request->contact_number)) {
            $user = $request->chatwoot_accoumts ? User::where('chatwoot_accoumts', $request->chatwoot_accoumts)->first() : null;
            if ($user) {
                SystemNotification::create([
                    'user_id' => $user->id,
                    'title' => 'Falha ao Formatar Número do Lead',
                    'message' => "Não foi possível formatar o número do lead com ID Card: {$idCard}. Nome do Lead: {$request->contact_name}. Número fornecido: {$request->contact_number}.",
                    'read' => false
                ]);
                Log::info("Notificação enviada para o usuário ID {$user->id} sobre falha na formatação do número do lead ID {$idCard}.");
            } else {
                Log::warning("Nenhum usuário encontrado para chatwoot_accoumts: {$request->chatwoot_accoumts}. Notificação de falha na formatação não enviada.");
            }
        }

        $emailVendedor = 'Não fornecido';
        $nomeVendedor = 'Não fornecido';
        if ($request->id_vendedor && $request->id_vendedor !== 'Não fornecido') {
            $vendedorInfo = $this->zohoCrmService->getUserEmailById($request->id_vendedor);
            if ($vendedorInfo) {
                $emailVendedor = $vendedorInfo['email'] ?? 'Não fornecido';
                $nomeVendedor = $vendedorInfo['name'] ?? 'Não fornecido';
            }
        }

        // Buscar lead no SyncFlowLeads por id_card ou contact_number
        $syncEmp = null;
        if ($idCard !== 'Não fornecido') {
            $syncEmp = SyncFlowLeads::where('id_card', $idCard)->first();
            Log::info("Busca no SyncFlowLeads por id_card {$idCard}: " . ($syncEmp ? 'Encontrado' : 'Não encontrado'));
        }
        if (!$syncEmp && $contactNumber !== 'Não fornecido') {
            $syncEmp = SyncFlowLeads::where('contact_number', $contactNumber)->first();
            Log::info("Busca no SyncFlowLeads por contact_number {$contactNumber}: " . ($syncEmp ? 'Encontrado' : 'Não encontrado'));
        }

        $chatwootStatus = 'pending';
        $contactId = $syncEmp ? $syncEmp->contact_id : null;
        $identifier = $syncEmp ? $syncEmp->identifier : null;

        if ($contactNumber !== 'Não fornecido' && $request->chatwoot_accoumts) {
            $user = User::where('chatwoot_accoumts', $request->chatwoot_accoumts)->first();
            if ($user && $user->token_acess) {
                // Gerar o identifier apenas se não existir
                if (!$identifier && $syncEmp && $syncEmp->id) {
                    $identifier = $syncEmp->id . '_' . now()->timestamp;
                    Log::info("Identifier gerado: {$identifier} para lead ID {$syncEmp->id}, id_card {$idCard}");
                } elseif (!$identifier && $idCard !== 'Não fornecido') {
                    // Caso não exista $syncEmp ainda (lead novo), gerar identifier curto baseado no número de telefone e timestamp
                    $base = $contactNumber !== 'Não fornecido' ? $contactNumber : uniqid();
                    $identifier = substr(md5($base), 0, 8) . '_' . substr(time(), -5);
                    Log::info("Identifier gerado (fallback): {$identifier} para id_card {$idCard}");
                }

                // Buscar contato no Chatwoot, priorizando identifier
                $contacts = [];
                if ($identifier) {
                    Log::info("Buscando contato no Chatwoot por identifier: {$identifier}, chatwoot_accoumts: {$request->chatwoot_accoumts}");
                    $contacts = $this->chatwootService->searchContatosApi($identifier, $request->chatwoot_accoumts, $user->token_acess);
                    Log::info("Resultado da busca por identifier {$identifier}: " . json_encode($contacts));
                }
                if (empty($contacts) && $contactNumber !== 'Não fornecido') {
                    Log::info("Buscando contato no Chatwoot por phone_number: {$contactNumber}, chatwoot_accoumts: {$request->chatwoot_accoumts}");
                    $contacts = $this->chatwootService->searchContatosApi($contactNumber, $request->chatwoot_accoumts, $user->token_acess);
                    Log::info("Resultado da busca por phone_number {$contactNumber}: " . json_encode($contacts));
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
                Log::info("Busca por {$contactNumber} ou identifier {$identifier} retornou " . count($contacts) . " contatos para id_card {$idCard}: " . json_encode($logContacts));

                if (is_array($contacts) && !empty($contacts)) {
                    $contact = $contacts[0];
                    Log::info("Contato selecionado para atualização: " . json_encode($contact));
                    SystemNotification::create([
                        'user_id' => $user->id,
                        'title' => 'Contato já existe no Chatwoot',
                        'message' => "O contato com o número {$contactNumber} já existe no Chatwoot. Contato Atualizado!",
                        'read' => false
                    ]);
                    Log::info("Notificação enviada para o usuário ID {$user->id} sobre contato existente no Chatwoot para id_card {$idCard}.");

                    // Usar id_contact se disponível, senão id
                    $contactIdForUpdate = isset($contact['id_contact']) ? $contact['id_contact'] : $contact['id'];
                    $contactData = $this->chatwootService->updateContact(
                        $request->chatwoot_accoumts,
                        $user->token_acess,
                        $contactIdForUpdate,
                        $request->contact_name ?? $contact['name'] ?? 'Não fornecido',
                        $request->contact_email !== 'Não fornecido' ? $request->contact_email : null,
                        $contact['identifier'] ?? $identifier
                    );
                    if ($contactData) {
                        Log::info("Contato atualizado no Chatwoot para id_card {$idCard}, número {$contactNumber}: ID {$contactIdForUpdate}");
                        $chatwootStatus = 'success';
                        $contactId = $contactData['contact_id'] ?? $contactIdForUpdate;
                        $identifier = $contactData['identifier'] ?? $identifier;
                    } else {
                        Log::error("Falha ao atualizar contato no Chatwoot para id_card {$idCard}, número {$contactNumber}: ID {$contactIdForUpdate}");
                        $chatwootStatus = 'success'; // Considerar sucesso, pois o contato já existe
                        $contactId = $contactIdForUpdate; // Usar o ID encontrado na busca
                    }
                } else {
                    try {
                        $contactData = $this->chatwootService->createContact(
                            $request->chatwoot_accoumts,
                            $user->token_acess,
                            $request->contact_name ?? 'Não fornecido',
                            $contactNumber,
                            $request->contact_email !== 'Não fornecido' ? $request->contact_email : null,
                            $identifier
                        );
                        if ($contactData) {
                            Log::info("Contato criado no Chatwoot para id_card {$idCard}, número {$contactNumber}, identifier {$identifier}, contact_id: {$contactData['contact_id']}");
                            $chatwootStatus = 'success';
                            $contactId = $contactData['contact_id'];
                            $identifier = $contactData['identifier'] ?? $identifier;
                        } else {
                            throw new \Exception("Resposta vazia ao criar contato no Chatwoot");
                        }
                    } catch (\Exception $e) {
                        Log::error("Erro ao criar contato no Chatwoot para id_card {$idCard}, número {$contactNumber}: {$e->getMessage()}");
                        if (strpos($e->getMessage(), '422') !== false && strpos($e->getMessage(), 'has already been taken') !== false) {
                            Log::info("Contato já existe no Chatwoot. Tentando recuperar contato existente para id_card {$idCard}, número {$contactNumber}");
                            $contacts = $this->chatwootService->searchContatosApi($contactNumber, $request->chatwoot_accoumts, $user->token_acess);
                            Log::info("Resultado da busca de recuperação por phone_number {$contactNumber}: " . json_encode($contacts));
                            if (is_array($contacts) && !empty($contacts)) {
                                $contact = $contacts[0];
                                $chatwootStatus = 'success';
                                $contactId = isset($contact['id_contact']) ? $contact['id_contact'] : $contact['id'];
                                $identifier = $contact['identifier'] ?? $identifier;
                                Log::info("Contato recuperado com sucesso no Chatwoot para id_card {$idCard}, número {$contactNumber}, contact_id: {$contactId}");
                            } else {
                                Log::error("Falha ao recuperar contato existente no Chatwoot para id_card {$idCard}, número {$contactNumber}");
                                $chatwootStatus = 'failed';
                            }
                        } else {
                            Log::error("Falha ao criar contato no Chatwoot para id_card {$idCard}, número {$contactNumber}: {$e->getMessage()}");
                            $chatwootStatus = 'failed';
                        }
                    }
                }
            } else {
                Log::error("Usuário ou token não encontrado para chatwoot_accoumts: {$request->chatwoot_accoumts}, id_card {$idCard}");
                $chatwootStatus = 'failed';
            }
        } else {
            Log::info("Número inválido ou chatwoot_accoumts não fornecido para id_card {$idCard}: {$contactNumber}");
            $chatwootStatus = 'skipped';
        }

        // Salvar ou atualizar o lead no SyncFlowLeads
        if ($syncEmp) {
            $oldEstagio = $syncEmp->estagio;
            $syncEmp->contact_name = $request->contact_name ?? $syncEmp->contact_name;
            $syncEmp->contact_number = $contactNumber;
            $syncEmp->contact_number_empresa = $contactNumberEmpresa;
            $syncEmp->contact_email = $request->contact_email ?? $syncEmp->contact_email;
            $syncEmp->estagio = $request->estagio ?? $syncEmp->estagio;
            $syncEmp->chatwoot_accoumts = $request->chatwoot_accoumts ?? $syncEmp->chatwoot_accoumts;
            $syncEmp->situacao_contato = $request->situacao_contato ?? $syncEmp->situacao_contato;
            $syncEmp->email_vendedor = $emailVendedor;
            $syncEmp->nome_vendedor = $nomeVendedor;
            $syncEmp->id_vendedor = $request->id_vendedor ?? $syncEmp->id_vendedor;
            $syncEmp->chatwoot_status = $chatwootStatus;
            $syncEmp->contact_id = $contactId;
            $syncEmp->identifier = $identifier;
            $syncEmp->updated_at = now();

            if ($request->cadencia_id) {
                $cadencia = Cadencias::find($request->cadencia_id);
                if ($cadencia) {
                    $syncEmp->cadencia_id = $request->cadencia_id;
                    Log::info("Cadência ID {$request->cadencia_id} atribuída diretamente ao lead ID {$syncEmp->id}, id_card {$idCard}");
                } else {
                    Log::warning("Cadência ID {$request->cadencia_id} não encontrada no banco de dados para id_card {$idCard}. Cadência não atribuída.");
                }
            }

            try {
                $syncEmp->save();
                Log::info("Lead existente atualizado com ID: {$syncEmp->id}, id_card {$idCard}, chatwoot_status: {$chatwootStatus}, contact_id: {$contactId}, identifier: {$identifier}");
            } catch (\Exception $e) {
                Log::error("Erro ao salvar lead existente no SyncFlowLeads para id_card {$idCard}: {$e->getMessage()}");
            }
        } else {
            $syncEmp = new SyncFlowLeads();
            $syncEmp->id_card = $idCard;
            $syncEmp->contact_name = $request->contact_name ?? 'Não fornecido';
            $syncEmp->contact_number = $contactNumber;
            $syncEmp->contact_number_empresa = $contactNumberEmpresa;
            $syncEmp->contact_email = $request->contact_email ?? 'Não fornecido';
            $syncEmp->estagio = $request->estagio ?? 'Não fornecido';
            $syncEmp->chatwoot_accoumts = $request->chatwoot_accoumts ?? null;
            $syncEmp->situacao_contato = $request->situacao_contato ?? 'Não fornecido';
            $syncEmp->email_vendedor = $emailVendedor;
            $syncEmp->nome_vendedor = $nomeVendedor;
            $syncEmp->id_vendedor = $request->id_vendedor ?? 'Não fornecido';
            $syncEmp->chatwoot_status = $chatwootStatus;
            $syncEmp->contact_id = $contactId;
            $syncEmp->identifier = $identifier;
            $syncEmp->created_at = now();

            if ($request->cadencia_id) {
                $cadencia = Cadencias::find($request->cadencia_id);
                if ($cadencia) {
                    $syncEmp->cadencia_id = $request->cadencia_id;
                    Log::info("Cadência ID {$request->cadencia_id} atribuída ao novo lead, id_card {$idCard}");
                } else {
                    Log::warning("Cadência ID {$request->cadencia_id} não encontrada no banco de dados para id_card {$idCard}. Cadência não atribuída.");
                }
            } else {
                Log::info("Nenhum cadência_id recebido; cadência não atribuída ao novo lead, id_card {$idCard}");
            }

            try {
                $syncEmp->save();
                Log::info("Novo lead salvo com ID: {$syncEmp->id}, id_card {$idCard}, chatwoot_status: {$chatwootStatus}, contact_id: {$contactId}, identifier: {$identifier}");
            } catch (\Exception $e) {
                Log::error("Erro ao salvar novo lead no SyncFlowLeads para id_card {$idCard}: {$e->getMessage()}");
            }

            try {
                $leadExists = $this->zohoCrmService->checkLeadExists($idCard);
                if ($leadExists) {
                    $response = $this->zohoCrmService->updateLeadStatusWhatsApp($idCard, 'Não respondido');
                    if ($response && isset($response['status']) && $response['status'] === 'success') {
                        Log::info("Status WhatsApp atualizado para 'Não respondido' para lead ID {$syncEmp->id}, id_card {$idCard}");
                    } else {
                        Log::error("Falha ao atualizar Status_WhatsApp para lead ID {$idCard}: " . json_encode($response));
                    }
                } else {
                    Log::error("Lead não encontrado no Zoho para ID {$idCard}. Atualização de Status_WhatsApp ignorada.");
                }
            } catch (\Exception $e) {
                Log::error("Erro ao atualizar Status_WhatsApp para lead ID {$idCard}: {$e->getMessage()}");
            }
        }

        if ($syncEmp && $contactNumber !== 'Não fornecido' && $syncEmp->cadencia_id) {
            try {
                $cadencia = Cadencias::find($syncEmp->cadencia_id);
                if (!$cadencia) {
                    Log::error("Cadência não encontrada para o lead", [
                        'lead_id' => $syncEmp->id,
                        'cadencia_id' => $syncEmp->cadencia_id,
                        'id_card' => $idCard
                    ]);
                    return response('Webhook received successfully', 200);
                }

                $evolution = Evolution::find($cadencia->evolution_id);
                if (!$evolution || !$evolution->api_post || !$evolution->apikey) {
                    Log::error("Caixa Evolution ou credenciais não encontradas para evolution_id: {$cadencia->evolution_id}", [
                        'lead_id' => $syncEmp->id,
                        'id_card' => $idCard
                    ]);
                    return response('Webhook received successfully', 200);
                }

                $conversation = ChatwootConversation::where('sync_flow_lead_id', $syncEmp->id)
                    ->where('status', 'open')
                    ->first();

                if ($conversation) {
                    Log::info("Dados usados para buscar agente: email_vendedor: {$syncEmp->email_vendedor}, chatwoot_account_id: {$request->chatwoot_accoumts}");

                    // Tentativa 1 Agente
                    $chatWootAgent = ChatwootsAgents::where('email', $syncEmp->email_vendedor)
                        ->where('chatwoot_account_id', $request->chatwoot_accoumts)
                        ->first();

                    // Tentativa 2 Agente
                    if (!$chatWootAgent && $emailVendedor && $emailVendedor !== 'Não fornecido') {
                        Log::info("Tentativa 2: buscando agente pelo email do vendedor: {$emailVendedor}");
                        $chatWootAgent = ChatwootsAgents::where('email', $emailVendedor)
                            ->where('chatwoot_account_id', $request->chatwoot_accoumts)
                            ->first();
                    }

                    Log::info("Agente encontrado: " . ($chatWootAgent ? 'Sim' : 'Não'), [
                        'email_vendedor' => $syncEmp->email_vendedor,
                        'chatwoot_account_id' => $request->chatwoot_accoumts,
                        'agent_id' => $chatWootAgent->agent_id ?? 'N/A'
                    ]);

                    Log::info("Dados para atribuição de agente: evolution_id: {$cadencia->evolution_id}, api_post: {$evolution->api_post}, apikey: {$evolution->apikey}, conversation_id: {$conversation->conversation_id}, agent_id: " . ($chatWootAgent->agent_id ?? 'N/A'));

                    if ($chatWootAgent && $chatWootAgent->agent_id) {
                        //$agents = $this->chatwootService->getAgents($evolution->api_post, $evolution->apikey);
                        //$matchingAgent = collect($agents)->firstWhere('email', $syncEmp->email_vendedor);

                        $this->chatwootService->assignAgentToConversation(
                            $evolution->api_post,
                            $evolution->apikey,
                            $conversation->conversation_id,
                            $chatWootAgent->agent_id
                        );

                        Log::info('Agente atribuído (ou reatribuído) à conversa pelo webhook Zoho', [
                            'conversation_id' => $conversation->conversation_id,
                            'agent_id' =>  $chatWootAgent->agent_id,
                            'lead_id' => $syncEmp->id,
                            'id_card' => $idCard,
                            'evolution_id' => $cadencia->evolution_id
                        ]);

                        $conversation->agent_assigned_once = true;
                        $conversation->agent_id =  $chatWootAgent->agent_id;
                        $conversation->save();

                    } else {
                        Log::warning('Agente Chatwoot não encontrado ou account_id não corresponde', [
                            'email_vendedor' => $syncEmp->email_vendedor,
                            'conversation_id' => $conversation->conversation_id,
                            'lead_id' => $syncEmp->id,
                            'evolution_id' => $cadencia->evolution_id
                        ]);
                    }
                } else {
                    Log::info('Nenhuma conversa aberta encontrada para o lead', [
                        'lead_id' => $syncEmp->id,
                        'id_card' => $idCard,
                        'contact_number' => $contactNumber
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Erro ao verificar ou atribuir agente à conversa no Chatwoot', [
                    'lead_id' => $syncEmp->id,
                    'id_card' => $idCard,
                    'error' => $e->getMessage(),
                    'evolution_id' => $cadencia->evolution_id ?? 'N/A'
                ]);
            }
        } else {
            Log::info('Não foi possível verificar conversas abertas: número inválido ou cadência não atribuída', [
                'id_card' => $idCard,
                'contact_number' => $contactNumber,
                'cadencia_id' => $syncEmp->cadencia_id ?? 'N/A'
            ]);
        }

        return response('Webhook received successfully', 200);
    }
}
