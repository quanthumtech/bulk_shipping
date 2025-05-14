<?php

namespace App\Http\Controllers;

use App\Models\CadenceMessage;
use App\Models\Cadencias;
use App\Models\SyncFlowLeads;
use App\Models\User;
use App\Models\Etapas;
use App\Models\Evolution;
use App\Services\ChatwootService;
use App\Services\ZohoCrmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        if ($length < 10 || $length > 11) {
            Log::warning("Número inválido, comprimento incorreto: {$number} (limpo: {$cleanNumber}, {$length} dígitos)");
            return 'Não fornecido';
        }

        if ($length === 11 && substr($cleanNumber, 0, 1) === '0') {
            $cleanNumber = substr($cleanNumber, 1);
        }

        $formattedNumber = '+55' . $cleanNumber;
        Log::info("Número formatado com sucesso: {$number} -> {$formattedNumber}");
        return $formattedNumber;
    }

    public function createFromWebhook(Request $request)
    {
        if ($request->isMethod('post') && $request->getContent()) {
            $idCard = $request->id_card ?? 'Não fornecido';
            $sync_emp = SyncFlowLeads::where('id_card', $idCard)->first();

            $contactNumber = $this->formatPhoneNumber($request->contact_number);
            $contactNumberEmpresa = $this->formatPhoneNumber($request->contact_number_empresa);

            $emailVendedor = 'Não fornecido';
            if ($request->id_vendedor && $request->id_vendedor !== 'Não fornecido') {
                $emailVendedor = $this->zohoCrmService->getUserEmailById($request->id_vendedor) ?? 'Não fornecido';
            }

            if ($sync_emp) {
                // Lead existe, atualiza as informações
                $oldEstagio = $sync_emp->estagio;
                $sync_emp->contact_name = $request->contact_name ?? $sync_emp->contact_name;
                $sync_emp->contact_number = $contactNumber;
                $sync_emp->contact_number_empresa = $contactNumberEmpresa;
                $sync_emp->contact_email = $request->contact_email ?? $sync_emp->contact_email;
                $sync_emp->estagio = $request->estagio ?? $sync_emp->estagio;
                $sync_emp->chatwoot_accoumts = $request->chatwoot_accoumts ?? $sync_emp->chatwoot_accoumts;
                $sync_emp->situacao_contato = $request->situacao_contato ?? $sync_emp->situacao_contato;
                $sync_emp->email_vendedor = $emailVendedor;
                $sync_emp->id_vendedor = $request->id_vendedor ?? $sync_emp->id_vendedor;
                $sync_emp->updated_at = now();

                // Atribuição de cadência
                if ($request->cadencia_id) {
                    $sync_emp->cadencia_id = $request->cadencia_id;
                    Log::info("Cadência ID {$request->cadencia_id} atribuída diretamente ao lead ID {$sync_emp->id}");
                } elseif ($sync_emp->estagio !== $oldEstagio && $sync_emp->estagio !== 'Não fornecido') {
                    $cadencia = Cadencias::whereRaw('UPPER(stage) = ?', [strtoupper($sync_emp->estagio)])
                        ->where('active', 1)
                        ->first();
                    if ($cadencia) {
                        if ($cadencia->ordem > 0) {
                            $sync_emp->cadencia_id = $cadencia->id;
                            Log::info("Cadência ID {$cadencia->id} (ordem {$cadencia->ordem}) atribuída ao lead ID {$sync_emp->id}");
                        } else {
                            Log::info("Cadência sem ordem definida para o estágio: {$sync_emp->estagio}. Mantendo cadência atual.");
                        }
                    } else {
                        $sync_emp->cadencia_id = null;
                        Log::info("Nenhuma cadência ativa encontrada para o estágio: {$sync_emp->estagio} do lead ID {$sync_emp->id}");
                    }
                }

                $sync_emp->save();
                Log::info("Lead existente atualizado com ID: {$sync_emp->id}");
            } else {
                // Novo lead
                $sync_emp = new SyncFlowLeads();
                $sync_emp->id_card = $idCard;
                $sync_emp->contact_name = $request->contact_name ?? 'Não fornecido';
                $sync_emp->contact_number = $contactNumber;
                $sync_emp->contact_number_empresa = $contactNumberEmpresa;
                $sync_emp->contact_email = $request->contact_email ?? 'Não fornecido';
                $sync_emp->estagio = $request->estagio ?? 'Não fornecido';
                $sync_emp->chatwoot_accoumts = $request->chatwoot_accoumts ?? null;
                $sync_emp->situacao_contato = $request->situacao_contato ?? 'Não fornecido';
                $sync_emp->email_vendedor = $emailVendedor;
                $sync_emp->id_vendedor = $request->id_vendedor ?? 'Não fornecido';
                $sync_emp->created_at = now();

                // Atribuição de cadência
                if ($request->cadencia_id) {
                    $sync_emp->cadencia_id = $request->cadencia_id;
                    Log::info("Cadência ID {$request->cadencia_id} atribuída ao novo lead");
                } elseif ($sync_emp->estagio !== 'Não fornecido') {
                    $cadencia = Cadencias::whereRaw('UPPER(stage) = ?', [strtoupper($sync_emp->estagio)])
                        ->where('active', 1)
                        ->first();
                    if ($cadencia) {
                        if ($cadencia->ordem > 0) {
                            $sync_emp->cadencia_id = $cadencia->id;
                            Log::info("Cadência ID {$cadencia->id} (ordem {$cadencia->ordem}) atribuída ao novo lead");
                        } else {
                            $sync_emp->cadencia_id = null;
                            Log::info("Cadência encontrada mas sem ordem definida para o estágio: {$sync_emp->estagio}");
                        }
                    } else {
                        $sync_emp->cadencia_id = null;
                        Log::info("Nenhuma cadência ativa encontrada para o estágio: {$sync_emp->estagio}");
                    }
                }

                $sync_emp->save();
                Log::info("Novo lead salvo com ID: {$sync_emp->id}");
            }

            // Criar contato no Chatwoot se o número for válido
            if ($contactNumber !== 'Não fornecido' && $sync_emp->chatwoot_accoumts) {
                $user = User::where('chatwoot_accoumts', $sync_emp->chatwoot_accoumts)->first();
                if ($user && $user->token_acess) {
                    // Verificar se o contato já existe
                    $existingContact = $this->chatwootService->searchContatosApi($contactNumber);
                    if (empty($existingContact)) {
                        // Criar novo contato
                        $contactData = $this->chatwootService->createContact(
                            $sync_emp->chatwoot_accoumts,
                            $user->token_acess,
                            $sync_emp->contact_name,
                            $contactNumber,
                            $sync_emp->contact_email !== 'Não fornecido' ? $sync_emp->contact_email : null
                        );
                        if ($contactData) {
                            Log::info("Contato criado no Chatwoot para o lead ID {$sync_emp->id}: " . json_encode($contactData));
                        } else {
                            Log::error("Falha ao criar contato no Chatwoot para o lead ID {$sync_emp->id}");
                        }
                    } else {
                        Log::info("Contato já existe no Chatwoot para o número {$contactNumber}");
                    }
                } else {
                    Log::error("Usuário ou token de acesso não encontrado para a conta Chatwoot: {$sync_emp->chatwoot_accoumts}");
                }
            } else {
                Log::info("Número de contato inválido ou conta Chatwoot não fornecida para o lead ID {$sync_emp->id}");
            }

            // Verifica se o número é WhatsApp
            if ($this->chatwootService->isWhatsappNumber($sync_emp->contact_number)) {
                $user = User::where('chatwoot_accoumts', $sync_emp->chatwoot_accoumts)->first();

                $Evolution = Evolution::where('user_id', $user->id)
                    ->where('active', 1)
                    ->first();

                if ($user && !empty($Evolution->api_post) && !empty($Evolution->apikey)) {
                    // Verifica status_whatsapp
                    if ($request->status_whatsapp === 'Contato Respondido') {
                        Log::info("Lead ID {$sync_emp->id} com status_whatsapp 'Contato Respondido'. Nenhuma etapa será executada.");
                        return response('Webhook received successfully', 200);
                    }

                    // Processa etapa imediata
                    if ($sync_emp->cadencia_id) {
                        $etapaImediata = Etapas::where('cadencia_id', $sync_emp->cadencia_id)
                            ->where('imediat', 1)
                            ->where('active', 1)
                            ->first();

                        if ($etapaImediata) {
                            Log::info("Etapa imediata encontrada: ID {$etapaImediata->id} para cadência {$sync_emp->cadencia_id}");
                            $this->chatwootService->sendMessage(
                                $sync_emp->contact_number,
                                $etapaImediata->message_content,
                                $Evolution->api_post,
                                $Evolution->apikey,
                                $sync_emp->contact_name,
                                $sync_emp->contact_email
                            );

                            $this->registrarEnvio($sync_emp, $etapaImediata);
                            Log::info("Mensagem da etapa imediata enviada para o lead {$sync_emp->id}");
                        } else {
                            Log::info("Nenhuma etapa imediata ativa encontrada para a cadência {$sync_emp->cadencia_id}");
                        }
                    } else {
                        Log::info("Nenhuma cadência associada ao lead {$sync_emp->id}");
                    }
                } else {
                    Log::error("Usuário ou configurações de API ausentes para a conta Chatwoot: {$sync_emp->chatwoot_accoumts}");
                }
            } else {
                Log::info("Número {$sync_emp->contact_number} não é um WhatsApp válido.");
            }

            return response('Webhook received successfully', 200);
        } else {
            return response('No data received', 400);
        }
    }

    protected function registrarEnvio($lead, $etapa)
    {
        CadenceMessage::create([
            'sync_flow_leads_id' => $lead->id,
            'etapa_id' => $etapa->id,
            'enviado_em' => now(),
        ]);
    }
}
