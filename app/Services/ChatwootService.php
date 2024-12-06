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
    public function getContatos()
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
                'page' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['payload'])) {
                    return collect($data['payload'])->map(function ($contact) {
                        return [
                            'id' => $contact['phone_number'],
                            'name' => $contact['name'] ?? $contact['phone_number'],
                        ];
                    })->toArray();
                }
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Erro ao recuperar contatos da API: ' . $e->getMessage());
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
