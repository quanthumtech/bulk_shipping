<?php

namespace App\Http\Controllers;

use App\Models\SyncFlowLeads;
use App\Models\User;
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
            $sync_emp->created_at = now();
            $sync_emp->save();

            Log::info("Novo lead salvo com ID: {$sync_emp->id}");

            // Verifica se o número é WhatsApp antes de enviar mensagem
            $this->chatwootService = app(ChatwootService::class);
            if ($this->chatwootService->isWhatsappNumber($sync_emp->contact_number)) {

                $user = User::where('chatwoot_accoumts', $sync_emp->chatwoot_accoumts)->first();

                if ($user) {
                    Log::info("Usuário encontrado: ID {$user->id} | API_POST: {$user->api_post} | APIKEY: {$user->apikey}");

                    // Verifica se as credenciais de API estão preenchidas
                    if (!empty($user->api_post) && !empty($user->apikey)) {
                        $this->chatwootService->sendMessage($sync_emp->contact_number, "Olá, recebemos seu contato!", $user->api_post, $user->apikey);
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
