<?php

/**
 * ZohoCrmService.php
 *
 * Serviço para interagir com a API do Zoho CRM.
 *
 * Documentação:
 * - Gerar token: https://www.zoho.com/creator/help/api/v2/generate-token.html
 * - API Console para Client ID e Client Secret: https://api-console.zoho.com/
 *
 * Exemplo de URL para obter o código de autorização:
 * https://accounts.zoho.com/oauth/v2/auth?response_type=code&client_id=1000.KF4FKZ401A1WZFJDKTSW08IL02QA6C&scope=ZohoCRM.settings.ALL,ZohoCRM.modules.ALL,ZohoCRM.users.ALL,ZohoCRM.users.READ&redirect_uri=https://bulkship.plataformamundo.com.br/login&access_type=offline
 *
 * Exemplo de URL para obter o refresh token:
 * https://accounts.zoho.com/oauth/v2/token?grant_type=authorization_code&code=<authorization_code>&client_id=1000.KF4FKZ401A1WZFJDKTSW08IL02QA6C&redirect_uri=https://bulkship.plataformamundo.com.br/login&client_secret=780abb844b0bfe2a751a14dcd3238fc8194210f67e
 *
 * Credenciais do banco de dados:
 * - client_id: 1000.KF4FKZ401A1WZFJDKTSW08IL02QA6C
 * - client_secret: 780abb844b0bfe2a751a14dcd3238fc8194210f67e
 * - refresh_token: 1000.80a781be97d62661b292b6056ab71569.240e7a177547e1a5bf5bc4f1db40b3a2
 */

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use App\Models\ZohoIntegration;
use Illuminate\Support\Facades\Log;

class ZohoCrmService
{
    protected $client;
    protected $userConfig;
    protected $zohoConfig;

    public function __construct(ZohoIntegration $zohoIntegration = null)
    {
        $this->client = new Client();
        $this->zohoConfig = $zohoIntegration;

        Log::info('ZohoIntegration model: ' . json_encode($zohoIntegration?->toArray() ?? []));

        $region = config('services.zoho.region', 'com');
        $tokenUrl = "https://accounts.zoho.{$region}/oauth/v2/token";
        $apiUrl = "https://www.zohoapis.{$region}/crm/v2";

        $this->userConfig = $zohoIntegration && $zohoIntegration->exists ? [
            'client_id'     => $zohoIntegration->client_id,
            'client_secret' => $zohoIntegration->client_secret,
            'refresh_token' => $zohoIntegration->refresh_token,
            'token_url'     => $tokenUrl,
            'api_url'       => $apiUrl,
            'redirect_uri'  => config('services.zoho.redirect_uri', 'https://bulkship.plataformamundo.com.br/login'),
        ] : config('services.zoho');

        if (empty($this->userConfig['client_id']) || empty($this->userConfig['client_secret'])) {
            Log::error('Zoho client_id or client_secret not configured.');
            throw new \Exception('Zoho client_id or client_secret not configured.');
        }

        if (empty($this->userConfig['refresh_token']) && $zohoIntegration?->exists) {
            Log::error('Zoho refresh token not configured for user: ' . ($zohoIntegration->user_id ?? 'unknown'));
            throw new \Exception('Zoho refresh token not configured for user.');
        }
    }

    /**
     * Obtain an access token from Zoho CRM, using cache if available.
     *
     * @return string
     * @throws \Exception
     */
    public function getAccessToken()
    {
        $cacheKey = 'zoho_access_token_' . md5($this->userConfig['client_id'] . $this->userConfig['refresh_token']);
        $token = Cache::get($cacheKey);

        if ($token) {
            Log::info('Access Token retrieved from cache for client_id: ' . $this->userConfig['client_id']);
            return $token;
        }

        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
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

                    // Update access_token and token_expires_at in the database
                    if ($this->zohoConfig) {
                        $this->zohoConfig->update([
                            'access_token' => $token,
                            'token_expires_at' => now()->addSeconds($expiresIn),
                        ]);
                    }

                    Log::info('New Access Token obtained for client_id: ' . $this->userConfig['client_id']);
                    return $token;
                } else {
                    Log::error('Error obtaining access token: ' . ($responseData['error'] ?? 'Invalid response'));
                    throw new \Exception('Error obtaining access token: ' . ($responseData['error'] ?? 'Invalid response'));
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 429) {
                    $attempts++;
                    $delay = pow(2, $attempts) * 10; // Exponential backoff: 10s, 20s, 40s
                    Log::warning("Rate limit reached when obtaining access token. Attempt {$attempts}/{$maxAttempts}. Waiting {$delay} seconds...");
                    sleep($delay);
                } else {
                    Log::error('Client exception when obtaining access token: ' . $e->getMessage());
                    throw $e;
                }
            } catch (\Exception $e) {
                report($e);
                Log::error('Exception when obtaining access token: ' . $e->getMessage());
                throw $e;
            }
        }

        throw new \Exception('Failed to obtain access token after ' . $maxAttempts . ' attempts due to rate limiting.');
    }

    /**
     * Exchange an authorization code for access and refresh tokens.
     *
     * @param string $code Authorization code from Zoho
     * @return array
     * @throws \Exception
     */
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

            $responseData = json_decode($response->getBody(), true);

            // Update ZohoIntegration with new tokens
            if ($this->zohoConfig && isset($responseData['refresh_token'])) {
                $this->zohoConfig->update([
                    'access_token' => $responseData['access_token'],
                    'refresh_token' => $responseData['refresh_token'],
                    'token_expires_at' => now()->addSeconds($responseData['expires_in']),
                ]);
            }

            return $responseData;
        } catch (\Exception $e) {
            report($e);
            Log::error('Error exchanging code for tokens: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get stages from the Potentials module in Zoho CRM.
     *
     * @return array
     * @throws \Exception
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

            Log::warning('Stage field not found in API response.');
            return [];
        } catch (\Exception $e) {
            report($e);
            Log::error('Error fetching stages from Zoho CRM: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the user's email by owner ID in Zoho CRM.
     *
     * @param string $ownerId ID of the deal owner
     * @return array|null Email and name of the user or null if not found
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

                Log::info("User information found for ID {$ownerId}: " . json_encode($userInfo));
                return $userInfo;
            } else {
                Log::warning("User information not found for ID {$ownerId}. Response: " . json_encode($userData));
                return null;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorBody = $e->getResponse()->getBody()->getContents();

            Log::error("Error fetching user information for ID {$ownerId}: Status {$statusCode}, Message: {$errorBody}");
            return null;
        } catch (\Exception $e) {
            report($e);
            Log::error("Exception fetching user information for ID {$ownerId}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Check if a lead exists in Zoho CRM.
     *
     * @param string $leadId ID of the lead (id_card)
     * @return bool
     */
    public function checkLeadExists($leadId)
    {
        $accessToken = $this->getAccessToken();
        try {
            $this->client->get("{$this->userConfig['api_url']}/Deals/{$leadId}", [
                'headers' => [
                    'Authorization' => "Zoho-oauthtoken {$accessToken}",
                ],
            ]);

            Log::info("Lead found: ID {$leadId}");
            return true;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            report($e);
            Log::error("Error checking lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return false;
        } catch (\Exception $e) {
            report($e);
            Log::error("Exception checking lead ID {$leadId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update the Status_WhatsApp field of a lead in Zoho CRM.
     *
     * @param string $leadId ID of the lead in Zoho CRM (id_card)
     * @param string $status New value for the Status_WhatsApp field
     * @return bool Success or failure of the update
     */
    public function updateLeadStatusWhatsApp($leadId, $status)
    {
        if (!$this->checkLeadExists($leadId)) {
            Log::error("Lead ID {$leadId} not found in Zoho CRM. Update aborted.");
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

            Log::info("Status_WhatsApp field updated to '{$status}' for lead ID {$leadId}");
            return true;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            report($e);
            Log::error("Error updating Status_WhatsApp for lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return false;
        } catch (\Exception $e) {
            report($e);
            Log::error("Exception updating Status_WhatsApp for lead ID {$leadId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the value of a specific field from a lead in Zoho CRM.
     *
     * @param string $leadId ID of the lead in Zoho CRM
     * @param string $field Name of the field to retrieve
     * @return string|null Field value or null if not found
     */
    public function getLeadField($leadId, $field)
    {
        if (!$this->checkLeadExists($leadId)) {
            Log::error("Lead ID {$leadId} not found in Zoho CRM.");
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

            Log::info("Field {$field} retrieved for lead ID {$leadId}: " . ($fieldValue ?? 'Null'));
            return $fieldValue;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            report($e);
            Log::error("Error retrieving field {$field} for lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return null;
        } catch (\Exception $e) {
            report($e);
            Log::error("Exception retrieving field {$field} for lead ID {$leadId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Register message history in the Historic_WhatsApp field in Zoho CRM.
     *
     * @param string $leadId ID of the lead in Zoho CRM
     * @param string $message Message to append to the history
     * @return bool Success or failure of the update
     */
    public function registerHistory($leadId, $message)
    {
        if (!$this->checkLeadExists($leadId)) {
            Log::error("Lead ID {$leadId} not found in Zoho CRM. History update aborted.");
            return false;
        }

        $accessToken = $this->getAccessToken();
        try {
            $currentHistory = $this->getLeadField($leadId, 'Historic_WhatsApp') ?? '';

            $timestamp = now()->format('Y-m-d H:i:s');
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

            Log::info("History updated for lead ID {$leadId}");
            return true;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            report($e);
            Log::error("Error updating history for lead ID {$leadId}: " . $e->getResponse()->getBody()->getContents());
            return false;
        } catch (\Exception $e) {
            report($e);
            Log::error("Exception updating history for lead ID {$leadId}: " . $e->getMessage());
            return false;
        }
    }
}