<?php

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
            //dd($responseData); // Adicione aqui para depurar

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
            Log::info('Resposta da API Zoho CRM:', $data);

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
}
