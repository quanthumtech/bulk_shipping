<?php

namespace App\Http\Controllers;

use App\Models\CadenceMessage;
use App\Models\Cadencias;
use App\Models\SyncFlowLeads;
use App\Models\User;
use App\Models\Etapas;
use App\Models\Evolution;
use App\Models\ChatwootConversation;
use App\Models\ChatwootsAgents;
use App\Services\ChatwootService;
use App\Services\ZohoCrmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
        // Log detalhado do webhook
        Log::info('Webhook request Zoho / Bulkship', [
            'method' => $request->method(),
            'content' => $request->getContent(),
            'headers' => $request->headers->all(),
            'id_card' => $request->id_card ?? 'Não fornecido'
        ]);

        // Verifica se o método é POST e se há conteúdo
        if (!$request->isMethod('post') || !$request->getContent()) {
            Log::error('Nenhum dado recebido no webhook');
            return response('No data received', 400);
        }

        // Verifica se o id_card está presente
        $idCard = $request->id_card ?? 'Não fornecido';

        // Formata os números de telefone
        $contactNumber = $this->formatPhoneNumber($request->contact_number);
        $contactNumberEmpresa = $this->formatPhoneNumber($request->contact_number_empresa);

        // Obtém informações do vendedor, se fornecido.
        $emailVendedor = 'Não fornecido';
        $nomeVendedor = 'Não fornecido';
        if ($request->id_vendedor && $request->id_vendedor !== 'Não fornecido') {
            $vendedorInfo = $this->zohoCrmService->getUserEmailById($request->id_vendedor);
            if ($vendedorInfo) {
                $emailVendedor = $vendedorInfo['email'] ?? 'Não fornecido';
                $nomeVendedor = $vendedorInfo['name'] ?? 'Não fornecido';
            }
        }

        /**
         * INFO: Verifica se o lead já existe no SyncFlowLeads e no Chatwoot.
         * Se existir, atualiza os dados; se não, cria um novo registro.
         * Usando o $chatwootStatus para rastrear o status do contato no Chatwoot.
         */
        $syncEmp = SyncFlowLeads::where('id_card', $idCard)->first();
        $chatwootStatus = 'pending';

        if ($contactNumber !== 'Não fornecido' && $request->chatwoot_accoumts) {
            $user = User::where('chatwoot_accoumts', $request->chatwoot_accoumts)->first();
            if ($user && $user->token_acess) {
                $contacts = $this->chatwootService->searchContatosApi(
                    $contactNumber,
                    $request->chatwoot_accoumts,
                    $user->token_acess
                );
                // Log simplificado e seguro
                $logContacts = is_array($contacts) ? array_map(function ($contact) {
                    return [
                        'id' => $contact['id'] ?? 'N/A',
                        'name' => $contact['name'] ?? 'N/A',
                        'phone_number' => $contact['phone_number'] ?? 'N/A',
                        'email' => $contact['email'] ?? 'N/A'
                    ];
                }, $contacts) : [];
                Log::info("Busca por {$contactNumber} retornou " . count($contacts) . " contatos para id_card {$request->id_card}: " . json_encode($logContacts));

                try {
                    if (is_array($contacts) && !empty($contacts)) {
                        // Atualizar contato existente
                        $contact = $contacts[0];
                        $contactData = $this->chatwootService->updateContact(
                            $request->chatwoot_accoumts,
                            $user->token_acess,
                            $contact['id_contact'],
                            $request->contact_name ?? $contact['name'] ?? 'Não fornecido',
                            $request->contact_email !== 'Não fornecido' ? $request->contact_email : null
                        );
                        if ($contactData) {
                            Log::info("Contato atualizado no Chatwoot para id_card {$idCard}, número {$contactNumber}: ID {$contact['id']}");
                            $chatwootStatus = 'success';
                        } else {
                            Log::error("Falha ao atualizar contato no Chatwoot para id_card {$idCard}, número {$contactNumber}: ID {$contact['id']}");
                            $chatwootStatus = 'failed';
                        }
                    } else {
                        // Criar novo contato
                        $contactData = $this->chatwootService->createContact(
                            $request->chatwoot_accoumts,
                            $user->token_acess,
                            $request->contact_name ?? 'Não fornecido',
                            $contactNumber,
                            $request->contact_email !== 'Não fornecido' ? $request->contact_email : null
                        );
                        if ($contactData) {
                            Log::info("Contato criado no Chatwoot para id_card {$idCard}, número {$contactNumber}");
                            $chatwootStatus = 'success';
                        } else {
                            Log::error("Falha ao criar contato no Chatwoot para id_card {$idCard}, número {$contactNumber}");
                            $chatwootStatus = 'failed';
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Erro ao processar contato no Chatwoot para id_card {$idCard}, número {$contactNumber}: {$e->getMessage()}");
                    $chatwootStatus = 'failed';
                }
            } else {
                Log::error("Usuário ou token não encontrado para chatwoot_accoumts: {$request->chatwoot_accoumts}, id_card {$idCard}");
                $chatwootStatus = 'failed';
            }
        } else {
            Log::info("Número inválido ou chatwoot_accoumts não fornecido para id_card {$idCard}: {$contactNumber}");
            $chatwootStatus = 'skipped';
        }

        /**
         * INFO: Atualiza ou cria o registro no SyncFlowLeads.
         */
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
            $syncEmp->updated_at = now();

            if ($request->cadencia_id) {
                $syncEmp->cadencia_id = $request->cadencia_id;
                Log::info("Cadência ID {$request->cadencia_id} atribuída diretamente ao lead ID {$syncEmp->id}, id_card {$idCard}");
            }

            $syncEmp->save();
            Log::info("Lead existente atualizado com ID: {$syncEmp->id}, id_card {$idCard}, chatwoot_status: {$chatwootStatus}");
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
            $syncEmp->created_at = now();

            if ($request->cadencia_id) {
                $syncEmp->cadencia_id = $request->cadencia_id;
                Log::info("Cadência ID {$request->cadencia_id} atribuída ao novo lead, id_card {$idCard}");
            } else {
                Log::info("Nenhum cadência_id recebido; cadência não atribuída ao novo lead, id_card {$idCard}");
            }

            $syncEmp->save();
            Log::info("Novo lead salvo com ID: {$syncEmp->id}, id_card {$idCard}, chatwoot_status: {$chatwootStatus}");

            // Validar ID antes de atualizar Status_WhatsApp
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

        /**
         * INFO: Verifica se o lead tem uma conversa aberta no Chatwoot e atribui o agente.
         */
        if ($syncEmp && $contactNumber !== 'Não fornecido' && $request->chatwoot_accoumts) {
            try {
                // Verificar se existe uma conversa aberta associada ao lead
                $conversation = ChatwootConversation::where('sync_flow_lead_id', $syncEmp->id)
                    ->where('status', 'open')
                    ->first();

                if ($conversation) {
                    // Encontrar o agente associado
                    $chatWootAgent = ChatwootsAgents::where('email', $syncEmp->email_vendedor)
                        ->where('chatwoot_account_id', $request->chatwoot_accoumts)
                        ->first();

                    if ($chatWootAgent) {
                        // Obter token de acesso
                        $user = User::where('chatwoot_accoumts', $request->chatwoot_accoumts)->first();
                        $apiToken = $user ? $user->token_acess : null;

                        if ($apiToken) {
                            // Verificar se o agente já foi atribuído
                            if (!$conversation->agent_assigned_once) {
                                // Obter lista de agentes do Chatwoot
                                $agents = $this->chatwootService->getAgents($request->chatwoot_accoumts, $apiToken);
                                $matchingAgent = collect($agents)->firstWhere('email', $syncEmp->email_vendedor);

                                if ($matchingAgent) {
                                    // Atribuir o agente à conversa
                                    $this->chatwootService->assignAgentToConversation(
                                        $request->chatwoot_accoumts,
                                        $apiToken,
                                        $conversation->conversation_id,
                                        $matchingAgent['agent_id']
                                    );
                                    Log::info('Agente atribuído à conversa pelo webhook Zoho', [
                                        'conversation_id' => $conversation->conversation_id,
                                        'agent_id' => $matchingAgent['agent_id'],
                                        'lead_id' => $syncEmp->id,
                                        'id_card' => $idCard
                                    ]);

                                    // Atualizar a flag agent_assigned_once
                                    $conversation->agent_assigned_once = true;
                                    $conversation->agent_id = $matchingAgent['agent_id'];
                                    $conversation->save();
                                } else {
                                    Log::warning('Agente não encontrado na lista de agentes do Chatwoot', [
                                        'email_vendedor' => $syncEmp->email_vendedor,
                                        'conversation_id' => $conversation->conversation_id,
                                        'lead_id' => $syncEmp->id
                                    ]);
                                }
                            } else {
                                Log::info('Agente já foi atribuído anteriormente à conversa', [
                                    'conversation_id' => $conversation->conversation_id,
                                    'lead_id' => $syncEmp->id
                                ]);
                            }
                        } else {
                            Log::warning('Token de acesso não encontrado para atribuir agente', [
                                'account_id' => $request->chatwoot_accoumts,
                                'lead_id' => $syncEmp->id
                            ]);
                        }
                    } else {
                        Log::warning('Agente Chatwoot não encontrado ou account_id não corresponde', [
                            'email_vendedor' => $syncEmp->email_vendedor,
                            'conversation_id' => $conversation->conversation_id,
                            'lead_id' => $syncEmp->id
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
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            Log::info('Não foi possível verificar conversas abertas: número inválido ou chatwoot_accoumts não fornecido', [
                'id_card' => $idCard,
                'contact_number' => $contactNumber
            ]);
        }
        /**
         * Fim: Verificação de conversa aberta e atribuição do agente.
         */

        return response('Webhook received successfully', 200);
    }

    /**
     * Registra o envio de uma mensagem para o
     * lead na etapa especificada.
     *
     * @param $lead
     * @param $etapa
     * @return void
     */
    protected function registrarEnvio($lead, $etapa)
    {
        CadenceMessage::create([
            'sync_flow_leads_id' => $lead->id,
            'etapa_id' => $etapa->id,
            'enviado_em' => now(),
        ]);
    }
}
