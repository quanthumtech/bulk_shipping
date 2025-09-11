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
 * Novas credenciais:
 * 
 * "access_token": "1000.5429c1804064453b97dbfd142b8f5a81.555e10d14306709fe97db158ef3fac01",
 * "refresh_token": "1000.bd1c35d7c4002ff862213a178a1caeea.b3f5b1a01c4161b1cf30abc739aa6332",
 * "scope": "ZohoCRM.settings.ALL ZohoCRM.modules.ALL ZohoCRM.users.ALL ZohoCRM.users.READ",
 * "api_domain": "https://www.zohoapis.com",
 * "token_type": "Bearer",
 * "expires_in": 3600
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
use App\Models\ZohoIntegration;

class ZohoCrmService
{
    protected $client;
    protected $userConfig;

    public function __construct(ZohoIntegration $zohoIntegration = null)
    {
        $this->client = new Client();

        $this->userConfig   = $zohoIntegration ? [
            'client_id'     => $zohoIntegration->client_id,
            'client_secret' => $zohoIntegration->client_secret,
            'refresh_token' => $zohoIntegration->refresh_token,
            'token_url'     => 'https://accounts.zoho.com/oauth/v2/token',
            'api_url'       => 'https://www.zohoapis.com/crm/v2',
            'redirect_uri'  => route('users.config', ['userId' => $zohoIntegration->user_id]),
        ] : config('services.zoho');

        if (empty($this->userConfig['refresh_token']) && $zohoIntegration) {
            //throw new \Exception('Zoho refresh token não configurado para o usuário.');
            logger()->error('Zoho refresh token não configurado para o usuário.');
        }
    }

    public function getAccessToken()
    {
        $cacheKey = 'zoho_access_token_' . md5($this->userConfig['client_id'] . $this->userConfig['refresh_token']);
        $token    = Cache::get($cacheKey);

        if ($token) {
            return $token;

            logger()->info('Access Token recuperado do cache para client_id: ' . $this->userConfig['client_id']);
        }

        try {
            $response = $this->client->post($this->userConfig['token_url'], [
                'form_params' => [
                    'grant_type'    => 'refresh_token',
                    'client_id'     => $this->userConfig['client_id'],
                    'client_secret' => $this->userConfig['client_secret'],
                    'refresh_token' => $this->userConfig['refresh_token'],
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            if (isset($responseData['access_token'])) {
                $token = $responseData['access_token'];
                $expiresIn = $responseData['expires_in'] ?? 3600;
                Cache::put($cacheKey, $token, $expiresIn - 60);
    
                logger()->info('Novo Access Token obtido para client_id: ' . $this->userConfig['client_id']);

                return $token;
            } else {
                logger()->error('Erro ao obter access token: ' . ($responseData['error'] ?? 'Resposta inválida'));
                throw new \Exception('Erro ao obter access token: ' . ($responseData['error'] ?? 'Resposta inválida'));
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 400 && strpos($e->getMessage(), 'You have made too many requests') !== false) {
                
                logger()->warning('Limite de taxa atingido ao obter access token. Aguardando retry...');
                sleep(10);
                return $this->getAccessToken();
            }

            logger()->error('Exceção ao obter access token: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            report($e);

            logger()->error('Exceção ao obter access token: ' . $e->getMessage());
            throw $e;
        }
    }

    public function exchangeCodeForTokens($code)
    {
        try {
            $response = $this->client->post($this->userConfig['token_url'], [
                'form_params' => [
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'client_id'     => $this->userConfig['client_id'],
                    'client_secret' => $this->userConfig['client_secret'],
                    'redirect_uri'  => $this->userConfig['redirect_uri'],
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            report($e);

            logger()->error('Erro ao trocar código por tokens: ' . $e->getMessage());
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
            $response = $this->client->get("{$this->userConfig['api_url']}/settings/fields", [
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

            logger()->warning('Campo Stage não encontrado na resposta da API.');
            return [];
        } catch (\Exception $e) {
            report($e);

            logger()->error('Erro ao buscar estágios do Zoho CRM: ' . $e->getMessage());
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
            $response = $this->client->get("{$this->userConfig['api_url']}/users/{$ownerId}", [
                'headers' => [
                    'Authorization' => "Zoho-oauthtoken {$accessToken}",
                ],
            ]);

            $userData = json_decode($response->getBody(), true);

            if (isset($userData['users'][0])) {
                $user = $userData['users'][0];
                $userInfo = [
                    'email' => $user['email'] ?? null,
                    'name' => $user['full_name'] ?? null
                ];

                logger()->info("Informações encontradas para o usuário ID {$ownerId}: " . json_encode($userInfo));
                return $userInfo;
            } else {
                logger()->warning("Informações não encontradas para o usuário ID {$ownerId}. Resposta: " . json_encode($userData));
                return null;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorBody = $e->getResponse()->getBody()->getContents();
          
            logger()->error("Erro ao buscar informações do usuário ID {$ownerId}: Status {$statusCode}, Mensagem: {$errorBody}");
            return null;
        } catch (\Exception $e) {
            report($e);

            logger()->error("Exceção ao buscar informações do usuário ID {$ownerId}: {$e->getMessage()}");
            return null;
        }
    }

    public function checkLeadExists($leadId)
    {
        $accessToken = $this->getAccessToken();
        try {
            $this->client->get("{$this->userConfig['api_url']}/Deals/{$leadId}", [
                'headers' => [
                    'Authorization' => "Zoho-oauthtoken {$accessToken}",
                ],
            ]);

            logger()->info("Lead encontrado: ID {$leadId}");
            return true;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            report($e);

            logger()->error("Erro ao verificar lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return false;
        } catch (\Exception $e) {
            report($e);

            logger()->error("Exceção ao verificar lead ID {$leadId}: " . $e->getMessage());
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
            logger()->error("Lead ID {$leadId} não encontrado no Zoho CRM. Atualização abortada.");
            return false;
        }

        $accessToken = $this->getAccessToken();
        try {
            $this->client->put("{$this->userConfig['api_url']}/Deals/{$leadId}", [
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

            logger()->info("Campo Status_WhatsApp atualizado para '{$status}' no lead ID {$leadId}");
            return true;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            report($e);

            logger()->error("Erro ao atualizar Status_WhatsApp para lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return false;
        } catch (\Exception $e) {
            report($e);

            logger()->error("Exceção ao atualizar Status_WhatsApp para lead ID {$leadId}: " . $e->getMessage());
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
            logger()->error("Lead ID {$leadId} não encontrado no Zoho CRM.");
            return null;
        }

        $accessToken = $this->getAccessToken();
        try {
            $response = $this->client->get("{$this->userConfig['api_url']}/Deals/{$leadId}", [
                'headers' => [
                    'Authorization' => "Zoho-oauthtoken {$accessToken}",
                ],
            ]);

            $leadData = json_decode($response->getBody(), true);
            $fieldValue = $leadData['data'][0][$field] ?? null;

            logger()->info("Campo {$field} recuperado para lead ID {$leadId}: " . ($fieldValue ?? 'Nulo'));
            return $fieldValue;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            report($e);

            logger()->error("Erro ao recuperar campo {$field} para lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return null;
        } catch (\Exception $e) {
            report($e);

            logger()->error("Exceção ao recuperar campo {$field} para lead ID {$leadId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Registrar hisotrico de mensagens no Zoho CRM.
     * Campo Historic_WhatsApp
     */
    public function registerHistory($leadId, $message)
    {
        if (!$this->checkLeadExists($leadId)) {
            logger()->error("Lead ID {$leadId} não encontrado no Zoho CRM. Atualização abortada.");
            return false;
        }

        $accessToken = $this->getAccessToken();
        try {
            $currentHistory = $this->getLeadField($leadId, 'Historic_WhatsApp') ?? '';

            $timestamp = date('Y-m-d H:i:s');
            $newHistory = $currentHistory
                ? $currentHistory . "\n[{$timestamp}] " . $message
                : "[{$timestamp}] " . $message;

            $this->client->put("{$this->userConfig['api_url']}/Deals/{$leadId}", [
                'headers' => [
                    'Authorization' => "Zoho-oauthtoken {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'data' => [
                        [
                            'Historic_WhatsApp' => $newHistory,
                        ],
                    ],
                ],
            ]);

            logger()->info("Histórico atualizado para lead ID {$leadId}");
            return true;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            report($e);

            logger()->error("Erro ao atualizar histórico para lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return false;
        } catch (\Exception $e) {
            report($e);

            logger()->error("Exceção ao atualizar histórico para lead ID {$leadId}: " . $e->getMessage());
            return false;
        }
    }
}
