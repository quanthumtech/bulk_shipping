<?php

namespace App\Services;

use App\Models\ListContatos;
use App\Models\SyncFlowLeads;
use App\Models\Versions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\WebhookLogService;

class ChatwootService
{
    public $apiBaseUrl = 'https://chatwoot.plataformamundo.com.br/api/v1/accounts/';
    protected $webhookLogService;

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
        $headers = [
            'api_access_token' => $tokenAcesso,
        ];

        // Concatena os critérios de ordenação em uma única string, se a API suportar múltiplos valores
        $sort = '-email,-name,-phone_number,-last_activity_at';

        $queryParams = [
            'sort' => $sort,
            'page' => $page,
            'name' => $search,         // Pesquisa por nome
            'email' => $search,        // Pesquisa por email
            'phone_number' => $search, // Pesquisa por telefone
        ];

        try {
            $response = Http::withHeaders($headers)
                    ->get($url, $queryParams);

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
     * Pesquisa contatos na API do Chatwoot.
     *
     * @param string $searchTerm Termo de busca (ex.: número de telefone, nome)
     * @param string|null $chatwootAccountId ID da conta no Chatwoot (opcional)
     * @param string|null $tokenAcesso Token de acesso da API (opcional)
     * @return array Lista de contatos encontrados
     */
    public function searchContatosApi($searchTerm, $chatwootAccountId = null, $tokenAcesso = null)
    {
        $searchTerm = (string) $searchTerm;

        // Se chatwootAccountId ou tokenAcesso não forem fornecidos, tente obter do usuário autenticado
        if ($chatwootAccountId === null || $tokenAcesso === null) {
            $user = Auth::user();
            if (!$user) {
                Log::error('Nenhum usuário autenticado e nenhum chatwootAccountId/tokenAcesso fornecido.');
                return [];
            }
            $chatwootAccountId = $chatwootAccountId ?? $user->chatwoot_accoumts;
            $tokenAcesso = $tokenAcesso ?? $user->token_acess;
        }

        $url = "https://chatwoot.plataformamundo.com.br/api/v1/accounts/{$chatwootAccountId}/contacts/search?q=" . urlencode($searchTerm);
        $headers = [
            'api_access_token' => $tokenAcesso,
        ];

        try {
            $response = Http::withHeaders($headers)->get($url);

            if (!$response->successful()) {
                Log::error('Erro na API de busca: Status ' . $response->status() . ' - Resposta: ' . $response->body());
                return [];
            }

            $data = $response->json();
            $contacts = $data['payload'] ?? [];

            Log::info("Contatos encontrados: " . json_encode($contacts));

            return collect($contacts)->map(function ($contact) {
                return [
                    'id' => $contact['phone_number'],
                    'name' => $contact['name'] ?? $contact['phone_number'],
                    'id_contact' => $contact['id'] ?? null,
                    'identifier' => $contact['identifier'] ?? null,
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Erro ao pesquisar contatos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém as conversas de um contato específico no Chatwoot.
     *
     * @param [type] $contactId
     * @param [type] $chatwootAccountId
     * @param [type] $tokenAcesso
     * @return void
     */
    public function getContactConversation($contactId, $chatwootAccountId = null, $tokenAcesso = null)
    {
        if ($chatwootAccountId === null || $tokenAcesso === null) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user) {
                Log::error('Nenhum usuário autenticado e nenhum chatwootAccountId/tokenAcesso fornecido.');
                return [];
            }
            $chatwootAccountId = $chatwootAccountId ?? $user->chatwoot_accoumts;
            $tokenAcesso = $tokenAcesso ?? $user->token_acess;
        }

        $url = $this->apiBaseUrl . $chatwootAccountId . '/contacts/' . $contactId . '/conversations';
        $headers = [
            'api_access_token' => $tokenAcesso,
        ];

        try {
            $response = Http::withHeaders($headers)->get($url);

            if (!$response->successful()) {
                Log::error('Erro ao buscar conversa: Status ' . $response->status() . ' - Resposta: ' . $response->body());
                return [];
            }

            $data = $response->json();
            Log::info("Conversa encontrada: " . json_encode($data));

            return collect($data['payload'] ?? $data)->map(function ($conversation) {
                return [
                    'id' => $conversation['id'],
                    'status' => $conversation['status'],
                    'created_at' => $conversation['created_at'],
                    'updated_at' => $conversation['updated_at'],
                    'last_activity_at' => $conversation['last_activity_at'] ?? $conversation['updated_at'],
                    'assignee_id' => $conversation['meta']['assignee']['id'] ?? null,
                    'messages' => collect($conversation['messages'] ?? [])->map(function ($message) {
                        return [
                            'message_id' => $message['id'],
                            'content' => $message['content'],
                            'created_at' => \Carbon\Carbon::parse($message['created_at'])->toDateTimeString(),
                            'sender_name' => $message['sender']['name'] ?? null,
                        ];
                    })->toArray(),
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Erro ao recuperar conversa: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém os agentes da conta do Chatwoot.
     *
     * @param string $chatwootAccountId
     * @param string $tokenAcesso
     * @return array
     */
    public function getAgents($chatwootAccountId, $tokenAcesso)
    {
        $url = "https://chatwoot.plataformamundo.com.br/api/v1/accounts/{$chatwootAccountId}/agents";
        $headers = [
            'api_access_token' => $tokenAcesso,
        ];

        try {
            $response = Http::withHeaders($headers)->get($url);

            if (!$response->successful()) {
                Log::error('Erro ao buscar agentes: Status ' . $response->status() . ' - Resposta: ' . $response->body());
                return [];
            }

            $agents = $response->json();

            Log::info("Agentes recuperados: " . json_encode($agents));

            return collect($agents)->map(function ($agent) {
                return [
                    'agent_id' => $agent['id'],
                    'name' => $agent['name'],
                    'email' => $agent['email'] ?? null,
                    'role' => $agent['role'],
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Erro ao recuperar agentes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Atribui um agente a uma conversa no Chatwoot.
     *
     * @param [type] $accountId
     * @param [type] $apiToken
     * @param [type] $conversationId
     * @param [type] $agentId
     * @return void
     */
    public function assignAgentToConversation($accountId, $apiToken, $conversationId, $agentId)
    {
        Log::info("Atribuindo agente {$agentId} à conversa {$conversationId} na conta {$accountId}, com token {$apiToken}");

        $response = Http::withHeaders([
            'api_access_token' => $apiToken
        ])->post($this->apiBaseUrl . $accountId . '/conversations/' . $conversationId . '/assignments', [
            'assignee_id' => $agentId
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to assign agent: ' . $response->body());
        }

        Log::info("Agente {$agentId} atribuído à conversa {$conversationId} com sucesso.");
        Log::info("Resposta da API: " . $response->body());

        return $response->json();
    }

    /**
     * Toggles the status of a conversation in Chatwoot.
     *
     * @param [type] $accountId
     * @param [type] $apiToken
     * @param [type] $conversationId
     * @return void
     */
    public function toggleConversationStatus($accountId, $apiToken, $conversationId)
    {
        $response = Http::withHeaders([
            'api_access_token' => $apiToken
        ])->post($this->apiBaseUrl . $accountId . '/conversations/' . $conversationId . '/toggle_status', [
            'status' => 'open'
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to toggle status: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Sinconiza os contatos da conta do Chatwoot
     *
     * @return void
     */
    public function syncContatos()
    {
        $user = Auth::user();
        $chatwootAccountId = $user->chatwoot_accoumts;
        $tokenAcesso = $user->token_acess;

        $url = "https://chatwoot.plataformamundo.com.br/api/v1/accounts/{$chatwootAccountId}/contacts";
        $headers = ['api_access_token' => $tokenAcesso];

        $page = 1;
        $perPage = 15;
        $totalContacts = 0;

        do {
            try {
                $response = Http::withHeaders($headers)->get($url, [
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

                Log::info("Resposta da API Chatwoot (Página $page): " . json_encode($response->json()));

                if (!$response->successful()) {
                    Log::error("Erro ao buscar contatos - Página: $page - Status: " . $response->status());
                    break;
                }

                $data = $response->json();

                if (isset($data['payload'])) {
                    $contactsRetrieved = $data['payload'];
                } elseif (isset($data['data'])) {
                    $contactsRetrieved = $data['data'];
                } else {
                    Log::warning("Nenhum contato encontrado na página $page.");
                    break;
                }

                if (empty($contactsRetrieved)) {
                    Log::info("Página $page retornou vazia. Encerrando sincronização.");
                    break;
                }

                foreach ($contactsRetrieved as $contact) {
                    if (!empty($contact['phone_number'])) {
                        if ($this->isWhatsappNumber($contact['phone_number'])) {
                            ListContatos::updateOrCreate(
                                ['phone_number' => $contact['phone_number']],
                                [
                                    'contact_name' => $contact['name'] ?? $contact['phone_number'],
                                    'chatwoot_id' => $chatwootAccountId
                                ]
                            );
                            $totalContacts++;
                            Log::info("Contato {$contact['phone_number']} sincronizado.");
                        } else {
                            Log::info("Contato {$contact['phone_number']} não é um número de WhatsApp.");
                        }
                    }
                }

                Log::info("Página $page sincronizada. Contatos processados até agora: $totalContacts.");

                $page++;
            } catch (\Exception $e) {
                Log::error("Erro ao sincronizar contatos: " . $e->getMessage());
                break;
            }
        } while (!empty($contactsRetrieved));

        Log::info("Sincronização finalizada. Total de contatos sincronizados: $totalContacts.");
    }

    /**
     * Cria um contato no Chatwoot.
     *
     * @param string $accountId ID da conta no Chatwoot
     * @param string $apiToken Token de acesso da API
     * @param string $name Nome do contato
     * @param string $phoneNumber Número de telefone
     * @param string|null $email Email do contato (opcional)
     * @return array|null Dados do contato criado ou null em caso de erro
     */
    public function createContact($accountId, $apiToken, $name, $phoneNumber, $email = null, $userId = null)
    {
        $url = "{$this->apiBaseUrl}{$accountId}/contacts";
        $headers = [
            'api_access_token' => $apiToken,
            'Content-Type' => 'application/json',
        ];

        $payload = [
            'name' => $name ?? 'Não fornecido',
            'phone_number' => $phoneNumber,
        ];

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $payload['email'] = $email;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(5)
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::error("Erro ao criar contato no Chatwoot para {$phoneNumber}: Status {$response->status()} - Resposta: {$response->body()}");
                return null;
            }

            $data = $response->json();
            Log::info("Contato criado com sucesso no Chatwoot para {$phoneNumber}: " . json_encode($data));
            $identifier = $data['payload']['identifier'] ?? null;

            // Ensure webhookLogService is available
            $this->webhookLogService = app(WebhookLogService::class);

            // Log the successful creation
            $this->webhookLogService->info("Contato criado com sucesso", [
                'phone_number' => $phoneNumber,
                'name' => $name,
                'email' => $email,
                'identifier' => $identifier,
            ], $accountId, $userId, 'zoho');

            return [
                'contact_id' => $data['payload']['id'] ?? null,
                'identifier' => $identifier,
                'payload' => $data['payload']
            ];
        } catch (\Exception $e) {
            Log::error("Erro ao criar contato no Chatwoot para {$phoneNumber}: {$e->getMessage()}");
            
            // Ensure webhookLogService is available
            $this->webhookLogService = app(WebhookLogService::class);

            $this->webhookLogService->error("Erro ao Criar o contato", [
                'phone_number' => $phoneNumber,
                'name' => $name,
                'email' => $email,
                'identifier' => $identifier,
                'exception' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ],
            ], $accountId, $userId, 'zoho');
            return null;
        }
    }

    /**
     * Atualiza um contato existente no Chatwoot.
     *
     * @param string $accountId ID da conta no Chatwoot
     * @param string $apiToken Token de acesso da API
     * @param string $contactId ID do contato no Chatwoot
     * @param string $name Nome do contato
     * @param string|null $email Email do contato (opcional)
     * @return array|null Dados do contato atualizado ou null em caso de erro
     */
    public function updateContact($accountId, $apiToken, $contactId, $name, $email = null, $userId = null)
    {
        $url = "{$this->apiBaseUrl}{$accountId}/contacts/{$contactId}";
        $headers = [
            'api_access_token' => $apiToken,
            'Content-Type' => 'application/json',
        ];

        $payload = [
            'name' => $name ?? 'Não fornecido',
        ];

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $payload['email'] = $email;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(5)
                ->put($url, $payload);

            if (!$response->successful()) {
                Log::error("Erro ao atualizar contato no Chatwoot (ID: {$contactId}): Status {$response->status()} - Resposta: {$response->body()}");
                return null;
            }

            $data = $response->json();
            Log::info("Contato atualizado com sucesso no Chatwoot (ID: {$contactId}): " . json_encode($data));

            // Define identifier before using it
            $identifier = $data['payload']['identifier'] ?? null;

            
            // Ensure webhookLogService is available
            $this->webhookLogService = app(WebhookLogService::class);

            // Log the successful update
            $this->webhookLogService->info("Contato atualizado com sucesso", [
                'contact_id' => $contactId,
                'name' => $name,
                'email' => $email,
                'identifier' => $identifier,
            ], $accountId, $userId, 'zoho');
            return [
                'contact_id' => $data['payload']['id'] ?? $contactId,
                'identifier' => $identifier,
                'payload' => $data['payload']
            ];
        } catch (\Exception $e) {
            Log::error("Erro ao atualizar contato no Chatwoot (ID: {$contactId}): {$e->getMessage()}");
            
            // Ensure webhookLogService is available
            $this->webhookLogService = app(WebhookLogService::class);

            $this->webhookLogService->error("Erro ao Atualizar o contato", [
                'contact_id' => $contactId,
                'name' => $name,
                'email' => $email,
                'identifier' => $identifier,
                'exception' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ],
            ], $accountId, $userId, 'zoho');
            return null;
        }
    }


    public function isWhatsappNumber($phoneNumber)
    {
        // Remove todos os caracteres que não sejam dígitos
        $digits = preg_replace('/\D/', '', $phoneNumber);

        // Se o número não começar com '55', o prefixa
        if (substr($digits, 0, 2) !== '55') {
            $digits = '55' . $digits;
        }

        return $digits;
    }

    /**
     * Envia uma mensagem (texto ou imagem) para a API externa.
     *
     * @param string $phoneNumber - O número de telefone do destinatário.
     * @param string $messageContent - O conteúdo da mensagem a ser enviada (texto ou URL da imagem).
     * @param string|null $nameLead - Nome do lead (opcional).
     * @param string|null $emailLead - Email do lead (opcional).
     * @param string|null $apiPost - URL da API para onde enviar a mensagem (opcional).
     * @param string|null $Apikey - Chave de API se não for obtida do usuário (opcional).
     * @return array|null - Resposta da API ou null em caso de erro.
     */
    public function sendMessage($phoneNumber, $messageContent, $apiPost = null, $Apikey = null, $nameLead = null, $emailLead = null, $agentName = null)
    {
        $phoneNumber = (string) $phoneNumber;
        $messageContent = (string) $messageContent;

        if (!$apiPost || !$Apikey) {
            Log::error("API Post ou API Key não fornecidos.");
            return null;
        }

        $apikey = $Apikey ?? null;
        $api_post = $apiPost ?? null;

        Log::info("API Post usada: {$api_post}");

        // Extrai a parte da URL até "sendText/"
        $needle = 'sendText/';
        if (strpos($api_post, $needle) !== false) {
            $baseUrl = substr($api_post, 0, strpos($api_post, $needle) + strlen($needle));
        } else {
            $baseUrl = $api_post;
        }

        Log::info("Base URL extraída: {$baseUrl}");

        // Verifica a versão ativa da API com base na parte extraída da URL
        $activeVersion = Versions::where('url_evolution', $baseUrl)
            ->value('type');

        Log::info("Versão ativa da API: {$activeVersion}");

        if (!$activeVersion) {
            Log::error("Nenhuma versão ativa da API encontrada.");
            return null;
        }

        Log::info("Nome do agente/vendedor encontrado: {$agentName}");

        // Verifica se é uma URL de imagem diretamente ou Markdown
        $isImage = preg_match('/^https?:\/\/.+\.(jpg|jpeg|png|gif)$/i', trim($messageContent)) ||
                preg_match('/!\[.*?\]\((https?:\/\/.*?)\)/', $messageContent, $matches);

        // Configuração do payload e endpoint com base na versão
        if ($activeVersion === '1' || $activeVersion === 1) {
            if ($isImage) {
                $imageUrl = $matches[1] ?? trim($messageContent);
                $caption = $matches ? trim(preg_replace('/!\[(.*?)\]\(.*\)/', '$1', $messageContent)) : '';

                // Determina o domínio base da aplicação
                $appUrl = config('app.url');
                $storagePrefix = $appUrl . '/storage/markdown/';

                // Verifica se é uma URL do storage local
                if (str_starts_with($imageUrl, $storagePrefix)) {
                    $fileName = basename($imageUrl);
                    $filePath = storage_path('app/public/markdown/' . $fileName);

                    if (!file_exists($filePath)) {
                        Log::error("Arquivo não encontrado: {$filePath}");
                        return null;
                    }

                    $fileSize = filesize($filePath);
                    if ($fileSize > 5 * 1024 * 1024) {
                        Log::error("Arquivo muito grande: {$fileSize} bytes em {$filePath}");
                        return null;
                    }

                    $imgContent = file_get_contents($filePath);
                    if ($imgContent === false) {
                        Log::error("Falha ao ler o arquivo: {$filePath}");
                        return null;
                    }
                } else {
                    $imgContent = file_get_contents($imageUrl);
                    if ($imgContent === false) {
                        Log::error("Falha ao baixar a imagem: {$imageUrl}");
                        return null;
                    }
                    $fileName = basename($imageUrl);
                }

                $media = base64_encode($imgContent);

                $payload = [
                    "number" => $phoneNumber,
                    "options" => [
                        "delay" => 1200,
                        "presence" => "composing"
                    ],
                    "mediaMessage" => [
                        "mediatype" => "image",
                        "fileName" => $fileName,
                        "caption" => $caption,
                        "media" => $media
                    ]
                ];
                $endpoint = str_replace('sendText', 'sendMedia', $api_post);

                Log::info("Payload sendo enviado para a API Evolution v1 (Imagem): " . json_encode($payload));
            } else {
                $messageContentFormat = $messageContent ?? 'Olá, Recebemos sua mensagem. Estamos verificando e logo entraremos em contato.';
                $messageContentFormat = str_replace(
                    ['#nome', '#email', '#agente'],
                    [$nameLead ?? 'Não fornecido', $emailLead ?? 'Não fornecido', $agentName, 'Não fornecido'],
                    $messageContentFormat
                );

                $payload = [
                    "number" => $phoneNumber,
                    "options" => [
                        "delay" => 1200,
                        "presence" => "composing",
                        "linkPreview" => false
                    ],
                    "textMessage" => [
                        "text" => $messageContentFormat
                    ]
                ];
                $endpoint = $api_post;

                Log::info("Payload sendo enviado para a API Evolution v1 (Text): " . json_encode($payload));
            }
        } elseif ($activeVersion === '2' || $activeVersion === 2) {
            if ($isImage) {
                $imageUrl = $matches[1] ?? trim($messageContent);
                $caption = $matches ? trim(preg_replace('/!\[(.*?)\]\(.*\)/', '$1', $messageContent)) : '';

                $payload = [
                    "number" => $phoneNumber,
                    "mediatype" => "image",
                    "mimetype" => "image/jpeg",
                    "caption" => $caption,
                    "media" => base64_encode(file_get_contents($imageUrl)),
                ];
                $endpoint = str_replace('sendText', 'sendMedia', $api_post);
            } else {
                $messageContentFormat = $messageContent ?? 'Olá, Recebemos sua mensagem. Estamos verificando e logo entraremos em contato.';
                $messageContentFormat = str_replace(
                    ['#nome', '#email', '#agente'],
                    [$nameLead ?? 'Não fornecido', $emailLead ?? 'Não fornecido', $agentName, 'Não fornecido'],
                    $messageContentFormat
                );

                $payload = [
                    "number" => $phoneNumber,
                    "text" => $messageContentFormat,
                ];
                $endpoint = $api_post;
            }

            Log::info("Payload sendo enviado para a API Evolution v2: " . json_encode($payload));
        } else {
            Log::error("Versão da API desconhecida: {$activeVersion}");
            return null;
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $apikey,
                'Content-Type' => 'application/json'
            ])->post($endpoint, $payload);

            Log::info("Resposta da API: " . $response->body());

            if ($response->successful()) {
                Log::info("Mensagem enviada com sucesso para {$phoneNumber}");
                return $response->json();
            } else {
                Log::error("Erro ao enviar mensagem: " . $response->body());
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Erro ao enviar mensagem: " . $e->getMessage());
            return null;
        }
    }


}
