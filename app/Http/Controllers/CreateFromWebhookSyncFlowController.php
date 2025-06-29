<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\SyncFlowLeads;
use App\Models\SystemNotification;
use App\Services\ChatwootService;
use App\Services\ZohoCrmService;

class CreateFromWebhookSyncFlowController extends WebhookZohoController
{
    protected $chatwootService;

    public function __construct(ChatwootService $chatwootService, ZohoCrmService $zohoCrmService)
    {
        parent::__construct($chatwootService, $zohoCrmService);
        $this->chatwootService = $chatwootService;
    }

    public function createFromWebhookSyncFlow(Request $request)
    {
        Log::info('Webhook request SyncFlow', [
            'method' => $request->method(),
            'content' => $request->getContent(),
            'headers' => $request->headers->all(),
        ]);

        if (!$request->isMethod('post') || !$request->getContent()) {
            Log::error('Nenhum dado recebido no webhook SyncFlow');
            return response('No data received', 400);
        }

        $contactNumber = $this->formatPhoneNumber($request->contact_number);
        $contactNumberEmpresa = $this->formatPhoneNumber($request->contact_number_empresa);

        if ($contactNumber === 'Não fornecido' && !empty($request->contact_number)) {
            $user = $request->chatwoot_accoumts ? User::where('chatwoot_accoumts', $request->chatwoot_accoumts)->first() : null;
            if ($user) {
                SystemNotification::create([
                    'user_id' => $user->id,
                    'title' => 'Falha ao Formatar Número do Lead',
                    'message' => "Não foi possível formatar o número do lead. Nome: {$request->contact_name}. Número: {$request->contact_number}.",
                    'read' => false
                ]);
            }
        }

        $syncEmp = SyncFlowLeads::where('contact_number', $contactNumber)->first();
        $chatwootStatus = 'pending';

        if ($contactNumber !== 'Não fornecido' && $request->chatwoot_accoumts) {
            $user = User::where('chatwoot_accoumts', $request->chatwoot_accoumts)->first();
            if ($user && $user->token_acess) {
                try {
                    $contacts = $this->chatwootService->searchContatosApi(
                        $contactNumber,
                        $request->chatwoot_accoumts,
                        $user->token_acess
                    );
                    Log::info("Busca por {$contactNumber} retornou " . count($contacts) . " contatos");

                    if (is_array($contacts) && !empty($contacts)) {
                        $contact = $contacts[0];
                        $contactData = $this->chatwootService->updateContact(
                            $request->chatwoot_accoumts,
                            $user->token_acess,
                            $contact['id_contact'],
                            $request->contact_name ?? $contact['name'] ?? 'Não fornecido',
                            $request->contact_email ?? null
                        );
                        $chatwootStatus = $contactData ? 'success' : 'failed';
                    } else {
                        $contactData = $this->chatwootService->createContact(
                            $request->chatwoot_accoumts,
                            $user->token_acess,
                            $request->contact_name ?? 'Não fornecido',
                            $contactNumber,
                            $request->contact_email ?? null
                        );
                        $chatwootStatus = $contactData ? 'success' : 'failed';
                    }
                } catch (\Exception $e) {
                    Log::error("Erro ao processar contato no Chatwoot: {$e->getMessage()}");
                    $chatwootStatus = 'failed';
                }
            } else {
                Log::error("Usuário ou token não encontrado para chatwoot_accoumts: {$request->chatwoot_accoumts}");
                $chatwootStatus = 'failed';
            }
        } else {
            Log::info("Número inválido ou chatwoot_accoumts não fornecido: {$contactNumber}");
            $chatwootStatus = 'skipped';
        }

        if ($syncEmp) {
            // NOTIFICAÇÃO: Atualiza o contato existente
            SystemNotification::create([
                'user_id' => $user->id,
                'title' => 'Contato já existe no Chatwoot',
                'message' => "O contato com o número {$contactNumber} já existe no Chatwoot. Contato Atualizado!",
                'read' => false
            ]);

            $syncEmp->update([
                'contact_name' => $request->contact_name ?? $syncEmp->contact_name,
                'contact_number' => $contactNumber,
                'contact_number_empresa' => $contactNumberEmpresa,
                'contact_email' => $request->contact_email ?? $syncEmp->contact_email,
                'estagio' => $request->estagio ?? $syncEmp->estagio,
                'chatwoot_accoumts' => $request->chatwoot_accoumts ?? $syncEmp->chatwoot_accoumts,
                'situacao_contato' => $request->situacao_contato ?? $syncEmp->situacao_contato,
                'cadencia_id' => $request->cadencia_id ?? $syncEmp->cadencia_id,
                'chatwoot_status' => $chatwootStatus,
                'updated_at' => now(),
            ]);
            Log::info("Lead existente atualizado com ID: {$syncEmp->id}");
        } else {
            // NOTIFICAÇÃO: Cria um novo contato
             SystemNotification::create([
                'user_id' => $user->id,
                'title' => 'Contato já existe no Chatwoot',
                'message' => "Novo contato criado com o número: {$contactNumber}, Nome: {$request->contact_name} no Chatwoot.",
                'read' => false
            ]);

            $syncEmp = SyncFlowLeads::create([
                'contact_name' => $request->contact_name ?? 'Não fornecido',
                'contact_number' => $contactNumber,
                'contact_number_empresa' => $contactNumberEmpresa,
                'contact_email' => $request->contact_email ?? 'Não fornecido',
                'estagio' => $request->estagio ?? 'Não fornecido',
                'chatwoot_accoumts' => $request->chatwoot_accoumts ?? null,
                'situacao_contato' => $request->situacao_contato ?? 'Não fornecido',
                'cadencia_id' => $request->cadencia_id ?? null,
                'chatwoot_status' => $chatwootStatus,
                'created_at' => now(),
            ]);
            Log::info("Novo lead salvo com ID: {$syncEmp->id}");
        }

        return response('Webhook SyncFlow received successfully', 200);
    }
}
