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

        // Remove o prefixo 0 no início do número
        if (substr($cleanNumber, 0, 1) === '0') {
            $cleanNumber = substr($cleanNumber, 1);
            $length = strlen($cleanNumber);
        }

        // Lista de DDDs válidos no Brasil
        $validDDDs = [
            11, 12, 13, 14, 15, 16, 17, 18, 19, // São Paulo
            21, 22, 24, 27, 28, // Rio de Janeiro e Espírito Santo
            31, 32, 33, 34, 35, 37, 38, // Minas Gerais
            41, 42, 43, 44, 45, 46, 47, 48, 49, // Paraná e Santa Catarina
            51, 53, 54, 55, // Rio Grande do Sul
            61, 62, 63, 64, 65, 66, 67, 68, 69, // Centro-Oeste
            71, 73, 74, 75, 77, 79, // Bahia e Sergipe
            81, 82, 83, 84, 85, 86, 87, 88, 89, // Nordeste
            91, 92, 93, 94, 95, 96, 97, 98, 99 // Norte
        ];

        if ($length < 10 || $length > 11) {
            Log::warning("Número inválido, comprimento incorreto: {$number} (limpo: {$cleanNumber}, {$length} dígitos)");
            return 'Não fornecido';
        }

        // Verifica se o DDD é válido
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
        // Log the incoming webhook request immediately
        Log::info('Webhook request Zoho / Bulkship', [
            'method' => $request->method(),
            'content' => $request->getContent(),
            'headers' => $request->headers->all()
        ]);

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
                        // Se existe uma cadência específica definida no modelo
                        if ($cadencia->ordem > 0) {
                            $sync_emp->cadencia_id = $cadencia->id;
                            Log::info("Cadência ID {$cadencia->id} (ordem {$cadencia->ordem}) atribuída ao lead ID {$sync_emp->id}");
                        } else {
                            // Se não tem ordem definida, mantém a cadência atual
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
                        // Se existe uma cadência específica definida no modelo
                        if ($cadencia->ordem > 0) {
                            $sync_emp->cadencia_id = $cadencia->id;
                            Log::info("Cadência ID {$cadencia->id} (ordem {$cadencia->ordem}) atribuída ao novo lead");
                        } else {
                            // Se não tem ordem definida, não atribui cadência
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

                // Setar o campo de Status WhatsApp como: Não respondido
                $this->zohoCrmService->updateLeadStatusWhatsApp($sync_emp->id_card, 'Não respondido');
                Log::info("Status WhatsApp atualizado para 'Não respondido' para o lead ID {$sync_emp->id}");
            }

            // Criar contato no Chatwoot se o número for válido
            if ($contactNumber !== 'Não fornecido' && $sync_emp->chatwoot_accoumts) {
                $user = User::where('chatwoot_accoumts', $sync_emp->chatwoot_accoumts)->first();
                if ($user && $user->token_acess) {
                    // Verificar se o contato já existe
                    $existingContact = $this->chatwootService->searchContatosApi(
                        $contactNumber,
                        $sync_emp->chatwoot_accoumts,
                        $user->token_acess
                    );
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
            /*
            if ($this->chatwootService->isWhatsappNumber($sync_emp->contact_number)) {
                // Processa etapa imediata
                if ($sync_emp->cadencia_id) {
                    $etapaImediata = Etapas::where('cadencia_id', $sync_emp->cadencia_id)
                        ->where('imediat', 1)
                        ->where('active', 1)
                        ->first();

                    if ($etapaImediata) {
                        $cadencia = Cadencias::find($sync_emp->cadencia_id);

                        if ($cadencia) {
                            $evolution = Evolution::where('id', $cadencia->evolution_id)
                                ->where('active', 1)
                                ->first();

                            if ($evolution && $evolution->api_post && $evolution->apikey) {
                                // Verifica status_whatsapp
                                if ($request->status_whatsapp === 'Contato Respondido') {
                                    Log::info("Lead ID {$sync_emp->id} com status_whatsapp 'Contato Respondido'. Nenhuma etapa será executada.");
                                    return response('Webhook received successfully', 200);
                                }

                                Log::info("Etapa imediata encontrada: ID {$etapaImediata->id} para cadência {$sync_emp->cadencia_id}");

                                $maxAttempts = 3;
                                $attempt = 1;

                                while ($attempt <= $maxAttempts) {
                                    try {
                                        $this->chatwootService->sendMessage(
                                            $sync_emp->contact_number,
                                            $etapaImediata->message_content,
                                            $evolution->api_post,
                                            $evolution->apikey,
                                            $sync_emp->contact_name,
                                            $sync_emp->contact_email
                                        );
                                        $this->registrarEnvio($sync_emp, $etapaImediata);
                                        Log::info("Mensagem da etapa imediata enviada para o lead {$sync_emp->id}");
                                        break;
                                    } catch (\Exception $e) {
                                        Log::error("Tentativa {$attempt} falhou para lead {$sync_emp->id}: " . $e->getMessage());
                                        if ($attempt === $maxAttempts) {
                                            Log::error("Falha definitiva ao enviar mensagem para lead {$sync_emp->id}");
                                            break;
                                        }
                                        sleep(5);
                                        $attempt++;
                                    }
                                }
                            } else {
                                Log::error("Caixa Evolution ou credenciais não encontradas para evolution_id: {$cadencia->evolution_id}");
                            }
                        } else {
                            Log::error("Cadência não encontrada para ID: {$sync_emp->cadencia_id}");
                        }
                    } else {
                        Log::info("Nenhuma etapa imediata ativa encontrada para a cadência {$sync_emp->cadencia_id}");
                    }
                } else {
                    Log::info("Nenhuma cadência associada ao lead {$sync_emp->id}");
                }
            } else {
                Log::info("Número {$sync_emp->contact_number} não é um WhatsApp válido.");
            }*/

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
