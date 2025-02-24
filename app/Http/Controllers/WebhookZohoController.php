<?php
namespace App\Http\Controllers;

use App\Models\SyncFlowLeads;
use App\Models\User;
use App\Models\Etapas;
use App\Services\ChatwootService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookZohoController extends Controller
{
    protected $chatwootService;

    public function createFromWebhook(Request $request)
    {
        if ($request->isMethod('post') && $request->getContent()) {
            // Criar nova instância de SyncFlowLeads
            $sync_emp = new SyncFlowLeads();

            $sync_emp->id_card = $request->id_card ?? 'Não fornecido';
            $sync_emp->contact_name = $request->contact_name ?? 'Não fornecido';
            $sync_emp->contact_number = $request->contact_number ?? 'Não fornecido';
            $sync_emp->contact_number_empresa = $request->contact_number_empresa ?? 'Não fornecido';
            $sync_emp->contact_email = $request->contact_email ?? 'Não fornecido';
            $sync_emp->estagio = $request->estagio ?? 'Não fornecido';
            $sync_emp->chatwoot_accoumts = $request->chatwoot_accoumts ?? null;
            $sync_emp->cadencia_id = $request->id_cadencia ?? null;
            $sync_emp->situacao_contato = $request->situacao_contato ?? 'Não fornecido'; //se é efetivo ou não
            $sync_emp->created_at = now();
            $sync_emp->save();

            Log::info("Novo lead salvo com ID: {$sync_emp->id}");

            // Inicializa o serviço Chatwoot
            $this->chatwootService = app(ChatwootService::class);

            // Verifica se o número é WhatsApp
            if ($this->chatwootService->isWhatsappNumber($sync_emp->contact_number)) {
                $user = User::where('chatwoot_accoumts', $sync_emp->chatwoot_accoumts)->first();

                if ($user) {
                    Log::info("Usuário encontrado: ID {$user->id} | API_POST: {$user->api_post} | APIKEY: {$user->apikey}");

                    // Verifica se as credenciais de API estão preenchidas
                    if (!empty($user->api_post) && !empty($user->apikey)) {
                        // Envia mensagem padrão
                        $this->chatwootService->sendMessage(
                            $sync_emp->contact_number,
                            "Olá, recebemos seu contato!",
                            $user->api_post,
                            $user->apikey
                        );

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
                                    $user->api_post,
                                    $user->apikey
                                );
                                Log::info("Mensagem da etapa imediata enviada para o lead {$sync_emp->id}");
                            } else {
                                Log::info("Nenhuma etapa imediata ativa encontrada para a cadência {$sync_emp->cadencia_id}");
                            }
                        } else {
                            Log::info("Nenhuma cadência associada ao lead {$sync_emp->id}");
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
}
