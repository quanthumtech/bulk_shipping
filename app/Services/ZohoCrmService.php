<?php

/**
 * ZohoCrmService.php
 *
 * Documentação:
 * zoho.com/creator/help/api/v2/generate-token.html
 *
 * Cliar o Cliente ID e o Client Secret:
 * https://api-console.zoho.com/
 *
 * Url para obter o access token:
 * https://accounts.zoho.com/oauth/v2/auth?response_type=code&client_id=1000.52CEX5NO0PL8FFZRD60P11GZK4E1NP&scope=ZohoCRM.settings.ALL,ZohoCRM.modules.ALL&redirect_uri=https://bulkship.plataformamundo.com.br/login&access_type=offline
 *
 * Exemplo de retorno:
 * https://bulkship.plataformamundo.com.br/login?code=1000.ea6b9ca02142a2d1877011941ac175ac.4b545a198be5640ae5d45cade56e5731&scope=ZohoCRM.settings.ALL,ZohoCRM.modules.ALL&state=state
 *
 * Esse código é o que você vai usar para obter o access token:
 * 1000.ea6b9ca02142a2d1877011941ac175ac.4b545a198be5640ae5d45cade56e5731
 *
 * Url para obter o refresh token:
 * https://accounts.zoho.com/oauth/v2/token?grant_type=authorization_code&code=1000.ea6b9ca02142a2d1877011941ac175ac.4b545a198be5640ae5d45cade56e5731&client_id=1000.52CEX5NO0PL8FFZRD60P11GZK4E1NP&redirect_uri=https://bulkship.plataformamundo.com.br/login&client_secret=1e84265ee0e4d47ecae8eff48b32edbf24bfa86e0b
 *
 *
 * grant_type: authorization_code
 * code: 1000.ea6b9ca02142a2d1877011941ac175ac.4b545a198be5640ae5d45cade56e5731
 * client_id: 1000.52CEX5NO0PL8FFZRD60P11GZK4E1NP
 * redirect_uri: https://bulkship.plataformamundo.com.br/login
 * client_secret: 1e84265ee0e4d47ecae8eff48b32edbf24bfa86e0b
 *
 * Serviço para interagir com a API do Zoho CRM.
 *
 * $accessToken = $this->getAccessToken(); Serviço para obter o access token do Zoho CRM.
 * Assim usado nas funções que envolve requisições a API do Zoho CRM e Outros serviços do Zoho.
 *
 */

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ZohoCrmService
{
    protected $client;
    protected $config;

    public function __construct()
    {
        $this->client = new Client();
        $this->config = config('services.zoho');

        if (empty($this->config['refresh_token'])) {
            throw new \Exception('Zoho refresh token não configurado.');
        }
    }

    /**
     * Obtém o access token do Zoho CRM usando o refresh token.
     *
     * @return void
     */
    public function getAccessToken()
    {
        try {
            $response = $this->client->post($this->config['token_url'], [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'refresh_token' => $this->config['refresh_token'],
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            if (isset($responseData['access_token'])) {
                Log::info('Novo Access Token obtido com sucesso: ' . $responseData['access_token']);
                return $responseData['access_token'];
            } else {
                Log::error('Erro ao obter o access token: ' . ($responseData['error'] ?? 'Resposta inválida'));
                throw new \Exception('Erro ao obter o access token: ' . ($responseData['error'] ?? 'Resposta inválida'));
            }
        } catch (\Exception $e) {
            Log::error('Exceção ao obter access token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtem os estágios do módulo Potentials do Zoho CRM.
     *
     * @return void
     */
    public function getStages()
    {
        $accessToken = $this->getAccessToken();

        try {
            $response = $this->client->get("{$this->config['api_url']}/settings/fields", [
                'query' => [
                    'module' => 'Potentials',
                ],
                'headers' => [
                    'Authorization' => "Zoho-oauthtoken {$accessToken}",
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            foreach ($data['fields'] as $field) {
                if ($field['api_name'] === 'Stage') {
                    return $field['pick_list_values'];
                }
            }

            Log::warning('Campo Stage não encontrado na resposta da API.');
            return [];
        } catch (\Exception $e) {
            Log::error('Erro ao buscar estágios do Zoho CRM: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtém o e-mail do usuário pelo ID do proprietário no Zoho CRM.
     *
     * @param string $ownerId ID do proprietário do negócio
     * @return string|null E-mail do usuário ou null se não encontrado
     */
    public function getUserEmailById($ownerId)
    {
        $accessToken = $this->getAccessToken();

        try {
            $response = $this->client->get("{$this->config['api_url']}/users/{$ownerId}", [
                'headers' => [
                    'Authorization' => "Zoho-oauthtoken {$accessToken}",
                ],
            ]);

            $userData = json_decode($response->getBody(), true);

            if (isset($userData['users'][0]['email'])) {
                Log::info("E-mail encontrado para o usuário ID {$ownerId}: {$userData['users'][0]['email']}");
                return $userData['users'][0]['email'];
            } else {
                Log::warning("E-mail não encontrado para o usuário ID {$ownerId}");
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Erro ao buscar e-mail do usuário ID {$ownerId}: {$e->getMessage()}");
            return null;
        }
    }
}
