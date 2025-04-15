<?php

namespace App\Services;

use App\Models\ListContatos;
use App\Models\Versions;
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

    public function searchContatosApi($searchTerm)
    {
        $searchTerm = (string) $searchTerm;
        $user = Auth::user();
        $chatwootAccountId = $user->chatwoot_accoumts;
        $tokenAcesso = $user->token_acess;

        // URL correta para busca
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

            return collect($contacts)->map(function ($contact) {
                return [
                    'id' => $contact['phone_number'],
                    'name' => $contact['name'] ?? $contact['phone_number'],
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Erro ao pesquisar contatos: ' . $e->getMessage());
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

    public function isWhatsappNumber($phoneNumber)
    {
        // Remove caracteres não numéricos
        $cleaned = preg_replace('/\D/', '', $phoneNumber);

        // Verifica se o número começa com o código do país (55 para o Brasil)
        // E se possui um tamanho compatível (13 ou 14 dígitos: 55 + DDD + número)
        if (strpos($cleaned, '55') === 0 && (strlen($cleaned) === 13 || strlen($cleaned) === 14)) {
            return true;
        }

        // Se preferir uma lógica mais genérica, pode utilizar outra abordagem,
        // por exemplo, verificar se o número possui pelo menos 10 dígitos
        // return strlen($cleaned) >= 10;

        return false;
    }

    /**
     * Envia uma mensagem (texto ou imagem) para a API externa.
     *
     * @param string $phoneNumber - O número de telefone do destinatário.
     * @param string $messageContent - O conteúdo da mensagem a ser enviada (texto ou URL da imagem).
     * @param string|null $apiPost - URL da API para onde enviar a mensagem (opcional).
     * @param string|null $Apikey - Chave de API se não for obtida do usuário (opcional).
     * @return array|null - Resposta da API ou null em caso de erro.
     */
    public function sendMessage($phoneNumber, $messageContent, $apiPost = null, $Apikey = null)
    {
        $phoneNumber = (string) $phoneNumber;
        $messageContent = (string) $messageContent;

        if (!$apiPost || !$Apikey) {
            Log::error("API Post ou API Key não fornecidos.");
            return null;
        }

        // Dados da API
        //$user = Auth::user();

        $apikey = $Apikey ?? null;
        $api_post = $apiPost ?? null;

        // Verifica a versão ativa da API
        $activeVersion = Versions::getActiveVersion();
        if (!$activeVersion) {
            Log::error("Nenhuma versão ativa da API encontrada.");
            return null;
        }

        // Verifica se é uma URL de imagem diretamente ou Markdown
        $isImage = preg_match('/^https?:\/\/.+\.(jpg|jpeg|png|gif)$/i', trim($messageContent)) ||
                preg_match('/!\[.*?\]\((https?:\/\/.*?)\)/', $messageContent, $matches);

        // Configuração do payload e endpoint com base na versão
        if ($activeVersion === 'Evolution v1') {
            if ($isImage) {
                $imageUrl = $matches[1] ?? trim($messageContent);
                $caption = $matches ? trim(preg_replace('/!\[(.*?)\]\(.*\)/', '$1', $messageContent)) : '';

                // Determina o domínio base da aplicação (funciona em local e produção)
                $appUrl = config('app.url'); // Ex.: http://localhost ou https://seudominio.com
                $storagePrefix = $appUrl . '/storage/markdown/';

                // Verifica se é uma URL do storage local
                if (str_starts_with($imageUrl, $storagePrefix)) {
                    $fileName = basename($imageUrl);
                    $filePath = storage_path('app/public/markdown/' . $fileName);

                    if (!file_exists($filePath)) {
                        Log::error("Arquivo não encontrado: {$filePath}");
                        return null;
                    }

                    // Limite de tamanho (ex.: 5MB)
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
                    // Para URLs externas, faz o download
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
            } else {
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
                $endpoint = $api_post;
            }
        } elseif ($activeVersion === 'Evolution v2') {
            if ($isImage) {
                $imageUrl = $matches[1] ?? trim($messageContent); // Usa URL do Markdown ou direta
                $caption = $matches ? trim(preg_replace('/!\[(.*?)\]\(.*\)/', '$1', $messageContent)) : '';

                $payload = [
                    "number" => $phoneNumber,
                    "mediatype" => "image", // Altere para o tipo de mídia correto, se necessário
                    "mimetype" => "image/jpeg", // Altere para o MIME type correto
                    "caption" => $caption,
                    "media" => base64_encode(file_get_contents($imageUrl)),
                    // "fileName" => "nome_do_arquivo.jpg", // Descomente e ajuste se necessário
                    // "delay" => 1200, // Descomente e ajuste se necessário
                    // "quoted" => [...], // Descomente e ajuste se necessário
                ];
                $endpoint = str_replace('sendText', 'sendMedia', $api_post);
            } else {
                $payload = [
                    "number" => $phoneNumber,
                    "text" => $messageContent,
                    // "delay" => 1200, // Descomente e ajuste se necessário
                    // "quoted" => [...], // Descomente e ajuste se necessário
                    // "linkPreview" => false, // Descomente e ajuste se necessário
                    // "mentionsEveryOne" => false, // Descomente e ajuste se necessário
                    // "mentioned" => [], // Descomente e ajuste se necessário
                ];
                $endpoint = $api_post;
            }
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
