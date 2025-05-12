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
        // Se o número já estiver no padrão exigido, retorna sem alterações
        if (preg_match('/^\+55\d{10,11}$/', $number)) {
            Log::info("Número já está no padrão: {$number}");
            return $number;
        }

        if (empty($number) || $number === 'Não fornecido') {
            Log::info("Número não fornecido ou vazio: {$number}");
            return 'Não fornecido';
        }

        // Remove todos os caracteres não numéricos
        $cleanNumber = preg_replace('/[^0-9]/', '', $number);

        // Verifica o comprimento do número limpo (10 ou 11 dígitos para DDD + número)
        $length = strlen($cleanNumber);
        if ($length < 10 || $length > 11) {
            Log::warning("Número inválido, comprimento incorreto: {$number} (limpo: {$cleanNumber}, {$length} dígitos)");
            return 'Não fornecido';
        }

        // Remove o 0 inicial, se presente (ex: 012988784433)
        if ($length === 11 && substr($cleanNumber, 0, 1) === '0') {
            $cleanNumber = substr($cleanNumber, 1);
        }

        // Adiciona o código +55
        $formattedNumber = '+55' . $cleanNumber;

        // Loga o sucesso da formatação
        Log::info("Número formatado com sucesso: {$number} -> {$formattedNumber}");
        return $formattedNumber;
    }

    public function createFromWebhook(Request $request)
    {
        if ($request->isMethod('post') && $request->getContent()) {
            // Busca lead existente pelo id_card
            $idCard = $request->id_card ?? 'Não fornecido';
            $sync_emp = SyncFlowLeads::where('id_card', $idCard)->first();

            // Formata os números de contato
            $contactNumber = $this->formatPhoneNumber($request->contact_number);
            $contactNumberEmpresa = $this->formatPhoneNumber($request->contact_number_empresa);

            // Busca o e-mail do vendedor com base no id_vendedor
            $emailVendedor = 'Não fornecido';
            if ($request->id_vendedor && $request->id_vendedor !== 'Não fornecido') {
                $emailVendedor = $this->zohoCrmService->getUserEmailById($request->id_vendedor) ?? 'Não fornecido';
            }

            if ($sync_emp) {
                // Lead existe, atualiza as informações
                $oldEstagio = $sync_emp->estagio; // Guarda o estágio antigo para comparação
                $sync_emp->contact_name = $request->contact_name ?? $sync_emp->contact_name;
                $sync_emp->contact_number = $contactNumber;
                $sync_emp->contact_number_empresa = $contactNumberEmpresa;
                $sync_emp->contact_email = $request->contact_email ?? $sync_emp->contact_email;
                $sync_emp->estagio = $request->estagio ?? $sync_emp->estagio;
                $sync_emp->chatwoot_accoumts = $request->chatwoot_accoumts ?? $sync_emp->chatwoot_accoumts;
                $sync_emp->cadencia_id = $request->id_cadencia ?? $sync_emp->cadencia_id;
                $sync_emp->situacao_contato = $request->situacao_contato ?? $sync_emp->situacao_contato;
                $sync_emp->email_vendedor = $emailVendedor;
                $sync_emp->id_vendedor = $request->id_vendedor ?? $sync_emp->id_vendedor;
                //$sync_emp->msg_content = $request->msg_content ?? $sync_emp->msg_content;
                $sync_emp->updated_at = now();

                // Se o estágio mudou e não veio cadencia_id, atualiza a cadência com base no novo estágio
                if (!$request->id_cadencia && $sync_emp->estagio !== $oldEstagio && $sync_emp->estagio !== 'Não fornecido') {
                    $cadencia = Cadencias::whereRaw('UPPER(stage) = ?', [strtoupper($sync_emp->estagio)])
                        ->where('active', 1)
                        ->first();
                    if ($cadencia) {
                        $sync_emp->cadencia_id = $cadencia->id;
                        Log::info("Cadência ID {$cadencia->id} atualizada para o lead ID {$sync_emp->id} com base no novo estágio: {$sync_emp->estagio}");
                    } else {
                        $sync_emp->cadencia_id = null;
                        Log::info("Nenhuma cadência ativa encontrada para o estágio: {$sync_emp->estagio} do lead ID {$sync_emp->id}, cadencia_id definido como null");
                    }
                }

                $sync_emp->save();

                Log::info("Lead existente atualizado com ID: {$sync_emp->id}");
            } else {
                // Lead não existe, cria um novo
                $sync_emp = new SyncFlowLeads();
                $sync_emp->id_card = $idCard;
                $sync_emp->contact_name = $request->contact_name ?? 'Não fornecido';
                $sync_emp->contact_number = $contactNumber;
                $sync_emp->contact_number_empresa = $contactNumberEmpresa;
                $sync_emp->contact_email = $request->contact_email ?? 'Não fornecido';
                $sync_emp->estagio = $request->estagio ?? 'Não fornecido';
                $sync_emp->chatwoot_accoumts = $request->chatwoot_accoumts ?? null;
                $sync_emp->cadencia_id = $request->id_cadencia ?? null;
                $sync_emp->situacao_contato = $request->situacao_contato ?? 'Não fornecido';
                $sync_emp->email_vendedor = $emailVendedor;
                $sync_emp->id_vendedor = $request->id_vendedor ?? 'Não fornecido';
                $sync_emp->created_at = now();

                // Se não veio cadencia_id mas tem estágio, busca a cadência pelo estágio
                if (!$sync_emp->cadencia_id && $sync_emp->estagio !== 'Não fornecido') {
                    $cadencia = Cadencias::whereRaw('UPPER(stage) = ?', [strtoupper($sync_emp->estagio)])
                        ->where('active', 1)
                        ->first();
                    if ($cadencia) {
                        $sync_emp->cadencia_id = $cadencia->id;
                        Log::info("Cadência ID {$cadencia->id} atribuída ao lead com base no estágio: {$sync_emp->estagio}");
                    } else {
                        $sync_emp->cadencia_id = null;
                        Log::info("Nenhuma cadência ativa encontrada para o estágio: {$sync_emp->estagio}, cadencia_id definido como null");
                    }
                }

                $sync_emp->save();

                Log::info("Novo lead salvo com ID: {$sync_emp->id}");
            }

            // Verifica se o número é WhatsApp
            if ($this->chatwootService->isWhatsappNumber($sync_emp->contact_number)) {
                $user = User::where('chatwoot_accoumts', $sync_emp->chatwoot_accoumts)->first();

                $Evolution = Evolution::where('user_id', $user->id)
                    ->where('active', 1)
                    ->first();

                if ($user) {
                    Log::info("Usuário encontrado: ID {$user->id} | API_POST: {$Evolution->api_post} | APIKEY: {$Evolution->apikey}");

                    if (!empty($Evolution->api_post) && !empty($Evolution->apikey)) {
                        // Envia mensagem padrão apenas para novos leads
                        if (!$sync_emp->wasRecentlyCreated) {
                            Log::info("Lead, ID: {$sync_emp->id}, Nome: {$sync_emp->name}. Já existia, mensagem padrão não enviada.");
                        } else {
                            /**
                             * O Envio da mensagem padrão para o Lead novo será feita na cadência.
                             * Esse recurso vai ficar desativado por enquanto, pois a mensagem padrão já é enviada na etapa imediata.
                             */

                            /*Log::info("Mensagem recebida webhook: {$request->msg_content}");
                            $message = $sync_emp->msg_content ?? $request->msg_content ?? 'Olá, recebemos sua mensagem e entraremos em contato em breve.';
                            $message = str_replace(
                                ['#nome', '#email'],
                                [$sync_emp->contact_name ?? 'Não fornecido', $sync_emp->contact_email ?? 'Não fornecido'],
                                $message
                            );
                            $this->chatwootService->sendMessage(
                                $sync_emp->contact_number,
                                $message,
                                $Evolution->api_post,
                                $Evolution->apikey
                            );*/
                        }

                        // Verifica se há cadência e etapa imediata
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
                            Log::info("Nenhuma cadência associada ao lead {$sync_emp->id}, cadencia_id está null");
                        }
                    } else {
                        Log::error("API_POST ou APIKEY ausentes para o usuário ID: {$user->id}");
                    }
                } else {
                    Log::error("Usuário não encontrado para a conta Chatwoot: {$sync_emp->chatwoot_accoumts}");
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
