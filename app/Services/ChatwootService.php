<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ChatwootService
{
    /**
     * Obtém os contatos da conta da
     * empresa do uauário.
     *
     * @return void
     */
    public function getContatos($page = 1, $search = '')
    {
        $user = Auth::user();
        $chatwootAccountId = $user->chatwoot_accoumts;
        $tokenAcesso = $user->token_acess;

        $url = "https://chatwoot.plataformamundo.com.br/api/v1/accounts/{$chatwootAccountId}/contacts";
        $token = $tokenAcesso;

        try {
            $response = Http::withHeaders([
                'api_access_token' => $token,
            ])->get($url, [
                'sort' => '-email',
                'sort' => '-name',
                'sort' => '-phone_number',
                'sort' => '-last_activity_at',
                'page' => $page,
                'name' => $search, // Passa o termo de busca para a API
                'email' => $search, // Pesquisa também por email
                'phone_number' => $search, // Pesquisa também por telefone
            ]);

            if (!$response->successful()) {
                Log::error('Erro na API: Status ' . $response->status() . ' - Resposta: ' . $response->body());
                return [];
            }

            $data = $response->json();
            Log::info("Página {$page} - Dados:", $data);

            // Obter os contatos da página atual
            $contacts = $data['payload'] ?? [];

            return collect($contacts)->map(function ($contact) {
                return [
                    'id' => $contact['phone_number'],
                    'name' => $contact['name'] ?? $contact['phone_number'],
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Erro ao recuperar contatos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Envia uma mensagem para a API externa.
     *
     * @param string $phoneNumber - O número de telefone do destinatário.
     * @param string $message - O conteúdo da mensagem a ser enviada.
     * @return void
     */
    public function sendMessage($phoneNumber, $messageContent)
    {
        $phoneNumber = (string) $phoneNumber;
        $messageContent = (string) $messageContent;

        //dados da api post
        $user = Auth::user();
        $apikey = $user->apikey;
        $api_post = $user->api_post;

        $payload = [
            "number" => $phoneNumber,
            "options" => [
                "delay" => 1200,
                "presence" => "composing",
                "linkPreview" => false
            ],
            "textMessage" => [
                "text" => $messageContent
            ]
        ];

        try {
            $response = Http::withHeaders([
                'apikey' => $apikey,
            ])->post($api_post, $payload);

            if ($response->successful()) {
                Log::info("Mensagem enviada com sucesso para {$phoneNumber}");
            } else {
                Log::error("Erro ao enviar mensagem: " . $response->body());
            }

        } catch (\Exception $e) {
            Log::error("Erro ao enviar mensagem: " . $e->getMessage());
        }
    }

}
