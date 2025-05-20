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

        if (!$request->isMethod('post') || !$request->getContent()) {
            Log::error('Nenhum dado recebido no webhook');
            return response('No data received', 400);
        }

        $idCard = $request->id_card ?? 'Não fornecido';
        $contactNumber = $this->formatPhoneNumber($request->contact_number);
        $contactNumberEmpresa = $this->formatPhoneNumber($request->contact_number_empresa);

        // Updated vendor information handling
        $emailVendedor = 'Não fornecido';
        $nomeVendedor = 'Não fornecido';
        if ($request->id_vendedor && $request->id_vendedor !== 'Não fornecido') {
            $vendedorInfo = $this->zohoCrmService->getUserEmailById($request->id_vendedor);
            if ($vendedorInfo) {
                $emailVendedor = $vendedorInfo['email'] ?? 'Não fornecido';
                $nomeVendedor = $vendedorInfo['name'] ?? 'Não fornecido';
            }
        }

        // Verificar se o lead já existe
        $syncEmp = SyncFlowLeads::where('id_card', $idCard)->first();

        // Processar integração com Chatwoot antes de salvar o lead
        $contactProcessed = false;
        $chatwootContactId = null;

        if ($contactNumber !== 'Não fornecido' && $request->chatwoot_accoumts) {
            $user = User::where('chatwoot_accoumts', $request->chatwoot_accoumts)->first();
            if ($user && $user->token_acess) {
                $maxAttempts = 3;
                $attempt = 1;

                while ($attempt <= $maxAttempts && !$contactProcessed) {
                    try {
                        // Verificar se o contato já existe no Chatwoot
                        $existingContact = $this->chatwootService->searchContatosApi(
                            $contactNumber,
                            $request->chatwoot_accoumts,
                            $user->token_acess
                        );

                        if (!empty($existingContact)) {
                            // Atualizar contato existente
                            $contactId = $existingContact[0]['id']; // Assumindo que o primeiro contato é o correto
                            $contactData = $this->chatwootService->updateContact(
                                $request->chatwoot_accoumts,
                                $user->token_acess,
                                $contactId,
                                $request->contact_name ?? 'Não fornecido',
                                $request->contact_email !== 'Não fornecido' ? $request->contact_email : null
                            );

                            if ($contactData) {
                                Log::info("Contato atualizado no Chatwoot: " . json_encode($contactData));
                                $contactProcessed = true;
                                $chatwootContactId = $contactId;
                            } else {
                                Log::error("Falha ao atualizar contato no Chatwoot para o número {$contactNumber}");
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
                                Log::info("Contato criado no Chatwoot: " . json_encode($contactData));
                                $contactProcessed = true;
                                $chatwootContactId = $contactData['id'] ?? null;
                            } else {
                                Log::error("Falha ao criar contato no Chatwoot para o número {$contactNumber}");
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Tentativa {$attempt} falhou ao processar contato no Chatwoot para número {$contactNumber}: {$e->getMessage()}");
                        if ($attempt === $maxAttempts) {
                            Log::error("Falha definitiva ao processar contato no Chatwoot para número {$contactNumber}");
                            // Opcional: Enviar notificação para administrador
                            return response('Failed to process contact in Chatwoot', 500);
                        }
                        sleep(2); // Aguarda 2 segundos antes da próxima tentativa
                        $attempt++;
                    }
                }
            } else {
                Log::error("Usuário ou token de acesso não encontrado para a conta Chatwoot: {$request->chatwoot_accoumts}");
                return response('Invalid Chatwoot account or token', 400);
            }
        } else {
            Log::info("Número de contato inválido ou conta Chatwoot não fornecida: {$contactNumber}");
            // Continuar mesmo que o Chatwoot não seja processado, se desejado
            $contactProcessed = true; // Considerar como processado para não bloquear o salvamento
        }

        // Prosseguir com o salvamento do lead apenas se o contato foi processado com sucesso
        if ($contactProcessed) {
            if ($syncEmp) {
                // Lead existe, atualiza as informações
                $oldEstagio = $syncEmp->estagio;
                $syncEmp->contact_name = $request->contact_name ?? $syncEmp->contact_name;
                $syncEmp->contact_number = $contactNumber;
                $syncEmp->contact_number_empresa = $contactNumberEmpresa;
                $syncEmp->contact_email = $request->contact_email ?? $syncEmp->contact_email;
                $syncEmp->estagio = $request->estalho ?? $syncEmp->estagio;
                $syncEmp->chatwoot_accoumts = $request->chatwoot_accoumts ?? $syncEmp->chatwoot_accoumts;
                $syncEmp->situacao_contato = $request->situacao_contato ?? $syncEmp->situacao_contato;
                $syncEmp->email_vendedor = $emailVendedor;
                $syncEmp->nome_vendedor = $nomeVendedor;
                $syncEmp->id_vendedor = $request->id_vendedor ?? $syncEmp->id_vendedor;
                $syncEmp->updated_at = now();

                // Atribuição de cadência somente se o lead vier com cadencia_id
                if ($request->cadencia_id) {
                    $syncEmp->cadencia_id = $request->cadencia_id;
                    Log::info("Cadência ID {$request->cadencia_id} atribuída diretamente ao lead ID {$syncEmp->id}");
                }

                $syncEmp->save();
                Log::info("Lead existente atualizado com ID: {$syncEmp->id}");
            } else {
                // Novo lead
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
                $syncEmp->created_at = now();

                // Atribuição de cadência
                if ($request->cadencia_id) {
                    $syncEmp->cadencia_id = $request->cadencia_id;
                    Log::info("Cadência ID {$request->cadencia_id} atribuída ao novo lead");
                } else {
                    Log::info("Nenhum cadência_id recebido; cadência não atribuída ao novo lead");
                }

                $syncEmp->save();
                Log::info("Novo lead salvo com ID: {$syncEmp->id}");

                // Setar o campo de Status WhatsApp como: Não respondido
                $this->zohoCrmService->updateLeadStatusWhatsApp($syncEmp->id_card, 'Não respondido');
                Log::info("Status WhatsApp atualizado para 'Não respondido' para o lead ID {$syncEmp->id}");
            }
        } else {
            Log::error("Lead não salvo devido a falha na integração com o Chatwoot para número {$contactNumber}");
            return response('Failed to save lead due to Chatwoot integration failure', 500);
        }

        // Verifica se o número é WhatsApp (mantendo a lógica comentada, caso queira reativar)
        /*
        if ($this->chatwootService->isWhatsappNumber($syncEmp->contact_number)) {
            // Processa etapa imediata
            if ($syncEmp->cadencia_id) {
                $etapaImediata = Etapas::where('cadencia_id', $syncEmp->cadencia_id)
                    ->where('imediat', 1)
                    ->where('active', 1)
                    ->first();

                if ($etapaImediata) {
                    $cadencia = Cadencias::find($syncEmp->cadencia_id);

                    if ($cadencia) {
                        $evolution = Evolution::where('id', $cadencia->evolution_id)
                            ->where('active', 1)
                            ->first();

                        if ($evolution && $evolution->api_post && $evolution->apikey) {
                            // Verifica status_whatsapp
                            if ($request->status_whatsapp === 'Contato Respondido') {
                                Log::info("Lead ID {$syncEmp->id} com status_whatsapp 'Contato Respondido'. Nenhuma etapa será executada.");
                                return response('Webhook received successfully', 200);
                            }

                            Log::info("Etapa imediata encontrada: ID {$etapaImediata->id} para cadência {$syncEmp->cadencia_id}");

                            $maxAttempts = 3;
                            $attempt = 1;

                            while ($attempt <= $maxAttempts) {
                                try {
                                    $this->chatwootService->sendMessage(
                                        $syncEmp->contact_number,
                                        $etapaImediata->message_content,
                                        $evolution->api_post,
                                        $evolution->apikey,
                                        $syncEmp->contact_name,
                                        $syncEmp->contact_email
                                    );
                                    $this->registrarEnvio($syncEmp, $etapaImediata);
                                    Log::info("Mensagem da etapa imediata enviada para o lead {$syncEmp->id}");
                                    break;
                                } catch (\Exception $e) {
                                    Log::error("Tentativa {$attempt} falhou para lead {$syncEmp->id}: " . $e->getMessage());
                                    if ($attempt === $maxAttempts) {
                                        Log::error("Falha definitiva ao enviar mensagem para lead {$syncEmp->id}");
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
                        Log::error("Cadência não encontrada para ID: {$syncEmp->cadencia_id}");
                    }
                } else {
                    Log::info("Nenhuma etapa imediata ativa encontrada para a cadência {$syncEmp->cadencia_id}");
                }
            } else {
                Log::info("Nenhuma cadência associada ao lead {$syncEmp->id}");
            }
        } else {
            Log::info("Número {$syncEmp->contact_number} não é um WhatsApp válido.");
        }
        */

        return response('Webhook received successfully', 200);
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
