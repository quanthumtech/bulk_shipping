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
 * https://accounts.zoho.com/oauth/v2/auth?response_type=code&client_id=1000.52CEX5NO0PL8FFZRD60P11GZK4E1NP&scope=ZohoCRM.settings.ALL,ZohoCRM.modules.ALL,ZohoCRM.users.ALL,ZohoCRM.users.READ&redirect_uri=https://bulkship.plataformamundo.com.br/login&access_type=offline
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
use Illuminate\Support\Facades\Cache;
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
        $cacheKey = 'zoho_access_token';
        $token = Cache::get($cacheKey);

        if ($token) {
            Log::info('Access Token recuperado do cache');
            return $token;
        }

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
                $token = $responseData['access_token'];
                $expiresIn = $responseData['expires_in'] ?? 3600;
                Cache::put($cacheKey, $token, $expiresIn - 60); // Cache for token lifetime minus 1 minute
                Log::info('Novo Access Token obtido e armazenado no cache: ' . $token);
                return $token;
            } else {
                Log::error('Erro ao obter o access token: ' . ($responseData['error'] ?? 'Resposta inválida'));
                throw new \Exception('Erro ao obter o access token: ' . ($responseData['error'] ?? 'Resposta inválida'));
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 400 && strpos($e->getMessage(), 'You have made too many requests') !== false) {
                Log::warning('Limite de taxa atingido ao obter access token. Aguardando retry...');
                sleep(10); // Espera 10 segundos antes de tentar novamente
                return $this->getAccessToken(); // Retry
            }
            Log::error('Exceção ao obter access token: ' . $e->getMessage());
            throw $e;
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

            if (isset($userData['users'][0]['email']) && !empty($userData['users'][0]['email'])) {
                Log::info("E-mail encontrado para o usuário ID {$ownerId}: {$userData['users'][0]['email']}");
                return $userData['users'][0]['email'];
            } else {
                Log::warning("E-mail não encontrado para o usuário ID {$ownerId}. Resposta: " . json_encode($userData));
                return null;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorBody = $e->getResponse()->getBody()->getContents();
            Log::error("Erro ao buscar e-mail do usuário ID {$ownerId}: Status {$statusCode}, Mensagem: {$errorBody}");
            return null;
        } catch (\Exception $e) {
            Log::error("Exceção ao buscar e-mail do usuário ID {$ownerId}: {$e->getMessage()}");
            return null;
        }
    }

    public function checkLeadExists($leadId)
    {
        $accessToken = $this->getAccessToken();
        try {
            $response = $this->client->get("{$this->config['api_url']}/Deals/{$leadId}", [
                'headers' => [
                    'Authorization' => "Zoho-oauthtoken {$accessToken}",
                ],
            ]);
            Log::info("Lead encontrado: ID {$leadId}");
            return true;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error("Erro ao verificar lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return false;
        } catch (\Exception $e) {
            Log::error("Exceção ao verificar lead ID {$leadId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza o campo Status_WhatsApp de um lead no Zoho CRM.
     *
     * @param string $leadId ID do lead no Zoho CRM (id_card)
     * @param string $status Novo valor para o campo Status_WhatsApp
     * @return bool Sucesso ou falha na atualização
     */
    public function updateLeadStatusWhatsApp($leadId, $status)
    {
        if (!$this->checkLeadExists($leadId)) {
            Log::error("Lead ID {$leadId} não encontrado no Zoho CRM. Atualização abortada.");
            return false;
        }

        $accessToken = $this->getAccessToken();
        try {
            $response = $this->client->put("{$this->config['api_url']}/Deals/{$leadId}", [
                'headers' => [
                    'Authorization' => "Zoho-oauthtoken {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'data' => [
                        [
                            'Status_WhatsApp' => $status,
                        ],
                    ],
                ],
            ]);
            Log::info("Campo Status_WhatsApp atualizado para '{$status}' no lead ID {$leadId}");
            return true;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error("Erro ao atualizar Status_WhatsApp para lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return false;
        } catch (\Exception $e) {
            Log::error("Exceção ao atualizar Status_WhatsApp para lead ID {$leadId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém o valor de um campo específico de um lead no Zoho CRM.
     *
     * @param string $leadId ID do lead no Zoho CRM
     * @param string $field Nome do campo a recuperar
     * @return string|null Valor do campo ou null se não encontrado
     */
    public function getLeadField($leadId, $field)
    {
        if (!$this->checkLeadExists($leadId)) {
            Log::error("Lead ID {$leadId} não encontrado no Zoho CRM.");
            return null;
        }

        $accessToken = $this->getAccessToken();
        try {
            $response = $this->client->get("{$this->config['api_url']}/Deals/{$leadId}", [
                'headers' => [
                    'Authorization' => "Zoho-oauthtoken {$accessToken}",
                ],
            ]);

            $leadData = json_decode($response->getBody(), true);
            $fieldValue = $leadData['data'][0][$field] ?? null;

            Log::info("Campo {$field} recuperado para lead ID {$leadId}: " . ($fieldValue ?? 'Nulo'));
            return $fieldValue;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error("Erro ao recuperar campo {$field} para lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return null;
        } catch (\Exception $e) {
            Log::error("Exceção ao recuperar campo {$field} para lead ID {$leadId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Registrar hisotrico de mensagens no Zoho CRM.
     * Campo Hist_rico_Atendimento
     */
    public function registerHistory($leadId, $message)
    {
        if (!$this->checkLeadExists($leadId)) {
            Log::error("Lead ID {$leadId} não encontrado no Zoho CRM. Atualização abortada.");
            return false;
        }

        $accessToken = $this->getAccessToken();
        try {
            $response = $this->client->put("{$this->config['api_url']}/Deals/{$leadId}", [
                'headers' => [
                    'Authorization' => "Zoho-oauthtoken {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'data' => [
                        [
                            'Hist_rico_Atendimento' => $message,
                        ],
                    ],
                ],
            ]);
            Log::info("Campo Hist_rico_Atendimento atualizado para '{$message}' no lead ID {$leadId}");
            return true;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error("Erro ao atualizar Hist_rico_Atendimento para lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return false;
        } catch (\Exception $e) {
            Log::error("Exceção ao atualizar Hist_rico_Atendimento para lead ID {$leadId}: " . $e->getMessage());
            return false;
        }
    }
}
