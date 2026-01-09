<?php

namespace App\Livewire;

use App\Enums\UserType;
use App\Livewire\Forms\UsersForm;
use App\Models\ChatwootsAgents;
use App\Models\Plan;
use App\Models\User;
use App\Models\Versions;
use App\Models\ZohoIntegration;
use App\Services\ChatwootService;
use App\Services\ZohoCrmService;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class UserConfigIndex extends Component
{
    use Toast, WithPagination;

    public UsersForm $form;
    public bool $editMode = false;
    public ?int $userId = null;
    public string $title = 'Cadastrar Usuário';
    public string $myTab = 'user-tab';
    public int $perPage = 5;
    public ?string $zoho_code = null;
    public ?int $zoho_integration_index = null;

    // Propriedades para o modal de solicitação de atualização de plano
    public bool $showPlanModal = false;
    public ?int $selectedPlanId = null;
    public ?string $planRequestReason = null;

    // Propriedades para o modal de solicitação de nova caixa Evolution
    public bool $showEvolutionModal = false;
    public string $evolutionInstanceName = '';
    public string $evolutionNumber = '';

    // Propriedades para estados expandidos das integrações Zoho
    public array $expandedZohoIntegrations = [];

    // Propriedades para o modal de solicitação de CRM
    public bool $showCrmModal = false;
    public string $crmName = '';
    public ?string $crmReason = null;

    public function mount($userId = null, $code = null, $zoho_index = null)
    {
        $this->userId = $userId;
        $this->zoho_code = $code;
        $this->zoho_integration_index = $zoho_index;

        if ($this->userId) {
            $user = User::find($this->userId);
            if ($user) {
                $this->form->setUsers($user);
                $this->form->plan_id = $user->plan_id ? $user->plan_id : '';
                $this->editMode = true;
                $this->title = 'Editar Usuário';
            } else {
                $this->error('Usuário não encontrado.', position: 'toast-top');
                return redirect()->route('users.index');
            }
        }

        // Inicializa os estados expandidos das integrações Zoho
        foreach ($this->form->zoho_integrations as $index => $integration) {
            $this->expandedZohoIntegrations[$index] = false;
        }

        if ($this->zoho_code && $this->zoho_integration_index !== null) {
            $this->handleZohoCallback();
        }
    }

    // Método para alternar o collapse de uma integração Zoho específica
    public function toggleZohoCollapse($index)
    {
        if (array_key_exists($index, $this->expandedZohoIntegrations)) {
            $this->expandedZohoIntegrations[$index] = !$this->expandedZohoIntegrations[$index];
        } else {
            $this->expandedZohoIntegrations[$index] = true;
        }
    }

    public function openPlanModal()
    {
        $this->showPlanModal = true;
        $this->selectedPlanId = null;
        $this->planRequestReason = null;
    }

    public function requestPlanUpdate(ChatwootService $chatwootService)
    {
        if (!$this->selectedPlanId) {
            $this->error('Selecione um plano para solicitar a atualização.', position: 'toast-top');
            return;
        }

        $currentUser = auth()->user();
        $selectedPlan = Plan::find($this->selectedPlanId);
        $currentPlan = $currentUser->plan ? $currentUser->plan->name : 'Nenhum plano';

        if (!$selectedPlan) {
            $this->error('Plano selecionado não encontrado.', position: 'toast-top');
            return;
        }

        $supportNumber = env('WHATSAPP_QUANTHUM_NUMBER');

        $message = "Solicitação de Atualização de Plano\n\n";
        $message .= "Usuário: {$currentUser->name}\n";
        $message .= "E-mail: {$currentUser->email}\n";
        $message .= "Plano Atual: {$currentPlan}\n";
        $message .= "Plano Solicitado: {$selectedPlan->name} (R$ " . number_format($selectedPlan->price ? $selectedPlan->price : 0, 2, ',', '.') . ")\n";
        $message .= "Motivo/Observações: {$this->planRequestReason}\n\n";
        $message .= "Por favor, entre em contato para prosseguir com a atualização.";

        $evolution = $currentUser->evolutions()->where('active', true)->first();
        if (!$evolution) {
            $this->error('Nenhuma caixa Evolution ativa encontrada para envio. Contate o administrador.', position: 'toast-top');
            return;
        }

        $result = $chatwootService->sendMessage(
            $supportNumber,
            $message,
            $evolution->api_post,
            $evolution->apikey,
            $currentUser->name,
            $currentUser->email
        );

        if ($result) {
            $this->success('Solicitação enviada com sucesso para o suporte via WhatsApp! Em breve entraremos em contato.', position: 'toast-top');
            $this->showPlanModal = false;
            $this->selectedPlanId = null;
        } else {
            $this->error('Erro ao enviar a solicitação. Tente novamente ou contate o suporte diretamente.', position: 'toast-top');
        }
    }

    public function openEvolutionModal()
    {
        $this->showEvolutionModal = true;
        $this->evolutionInstanceName = '';
        $this->evolutionNumber = '';
    }

    public function requestEvolution(ChatwootService $chatwootService)
    {
        if (empty($this->evolutionInstanceName) || empty($this->evolutionNumber)) {
            $this->error('Nome da instância e número são obrigatórios para solicitar a nova caixa.', position: 'toast-top');
            return;
        }

        $currentUser = auth()->user();

        $supportNumber = env('WHATSAPP_QUANTHUM_NUMBER');

        $message = "Solicitação de Nova Caixa Evolution\n\n";
        $message .= "Usuário: {$currentUser->name}\n";
        $message .= "E-mail: {$currentUser->email}\n";
        $message .= "Nome da Instância Desejada: {$this->evolutionInstanceName}\n";
        $message .= "Número do WhatsApp: {$this->evolutionNumber}\n\n";
        $message .= "Por favor, configure e ative a nova instância Evolution para este usuário.";

        $evolution = $currentUser->evolutions()->where('active', true)->first();
        if (!$evolution) {
            $this->warning('Nenhuma caixa Evolution ativa para envio da solicitação. Use o botão abaixo para contatar diretamente.', ['position' => 'toast-top']);
            return;
        }

        $result = $chatwootService->sendMessage(
            $supportNumber,
            $message,
            $evolution->api_post,
            $evolution->apikey,
            $currentUser->name,
            $currentUser->email
        );

        if ($result) {
            $this->success('Solicitação de nova caixa enviada com sucesso para o suporte via WhatsApp! Em breve configuraremos para você.', ['position' => 'toast-top']);
            $this->showEvolutionModal = false;
            $this->evolutionInstanceName = '';
            $this->evolutionNumber = '';
            // Opcional: Adicionar uma evolução vazia para o usuário preencher após aprovação
            // $this->form->addEvolution();
        } else {
            $this->error('Erro ao enviar a solicitação. Tente novamente ou contate o suporte diretamente.', position: 'toast-top');
        }
    }

    public function openCrmRequestModal()
    {
        $this->showCrmModal = true;
        $this->crmName = '';
        $this->crmReason = null;
    }

   public function requestCrmIntegration(ChatwootService $chatwootService)
    {
        if (empty($this->crmName)) {
            $this->error('Nome do CRM é obrigatório para solicitar a integração.', null, position: 'toast-top');
            return;
        }

        $currentUser = auth()->user();

        $supportNumber = env('WHATSAPP_QUANTHUM_NUMBER');

        $message = "Solicitação de Nova Integração CRM\n\n";
        $message .= "Usuário: {$currentUser->name}\n";
        $message .= "E-mail: {$currentUser->email}\n";
        $message .= "CRM Desejado: {$this->crmName}\n";
        $message .= "Detalhes/Motivo: " . ($this->crmReason ? $this->crmReason : 'Não informado') . "\n\n";
        $message .= "Por favor, configure e ative a integração com este CRM para o usuário.";

        $evolution = $currentUser->evolutions()->where('active', true)->first();
        if (!$evolution) {
            $this->warning('Nenhuma caixa Evolution ativa para envio da solicitação. Use o botão abaixo para contatar diretamente.', null, position: 'toast-top');
            return;
        }

        $result = $chatwootService->sendMessage(
            $supportNumber,
            $message,
            $evolution->api_post,
            $evolution->apikey,
            $currentUser->name,
            $currentUser->email
        );

        if ($result) {
            $this->success('Solicitação de nova integração CRM enviada com sucesso para o suporte via WhatsApp! Em breve configuraremos para você.', null, position: 'toast-top');
            $this->showCrmModal = false;
            $this->crmName = '';
            $this->crmReason = null;
        } else {
            $this->error('Erro ao enviar a solicitação. Tente novamente ou contate o suporte diretamente.', null, position: 'toast-top');
        }
    }

    public function handleZohoCallback()
    {
        try {
            $zohoIntegration = $this->form->zoho_integrations[$this->zoho_integration_index] ?? null;
            if (!$zohoIntegration) {
                $this->error('Integração Zoho não encontrada.', position: 'toast-top');
                return;
            }

            $zohoService = new ZohoCrmService(new ZohoIntegration([
                'user_id' => $this->userId,
                'client_id' => $zohoIntegration['client_id'],
                'client_secret' => $zohoIntegration['client_secret'],
                'refresh_token' => null,
            ]));

            $tokenData = $zohoService->exchangeCodeForTokens($this->zoho_code);

            logger()->info('Retorno Token: ', $tokenData);

            if (isset($tokenData['refresh_token'])) {
                $this->form->zoho_integrations[$this->zoho_integration_index]['refresh_token'] = $tokenData['refresh_token'];
                $this->form->zoho_integrations[$this->zoho_integration_index]['code'] = '';

                $this->success('Integração com Zoho CRM configurada com sucesso!', position: 'toast-top');
            } else {
                $this->error('Erro ao obter refresh token do Zoho CRM.', position: 'toast-top');
            }
        } catch (\Exception $e) {
            report($e);

            $this->error('Erro ao processar integração com Zoho CRM: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function processZohoCode($index)
    {
        $zohoIntegration = $this->form->zoho_integrations[$index] ?? null;
        if (!$zohoIntegration || empty($zohoIntegration['client_id']) || empty($zohoIntegration['client_secret']) || empty($zohoIntegration['code'])) {
            $this->error('Client ID, Client Secret e Código são obrigatórios.', position: 'toast-top');
            return;
        }

        try {
            $zohoService = new ZohoCrmService(new ZohoIntegration([
                'user_id' => $this->userId,
                'client_id' => $zohoIntegration['client_id'],
                'client_secret' => $zohoIntegration['client_secret'],
                'refresh_token' => null,
            ]));

            $tokenData = $zohoService->exchangeCodeForTokens($zohoIntegration['code']);

            logger()->info('Retorno Token: ', $tokenData);

            if (isset($tokenData['refresh_token'])) {
                $this->form->zoho_integrations[$index]['refresh_token'] = $tokenData['refresh_token'];
                $this->form->zoho_integrations[$index]['code'] = '';
                $this->success('Código processado com sucesso! Refresh token obtido.', position: 'toast-top');
            } else {
                $this->error('Erro ao obter refresh token do Zoho CRM.', position: 'toast-top');
            }
        } catch (\Exception $e) {
            report($e);

            $this->error('Erro ao processar o código Zoho: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function save()
    {
        try {
            if ($this->editMode) {
                $this->form->update();

                $this->success('Usuário atualizado com sucesso!', position: 'toast-top');
            } else {
                $this->form->store();

                $this->success('Usuário cadastrado com sucesso!', position: 'toast-top');
            }

            return redirect()->route('users.index');
        } catch (\Exception $e) {
            report($e);

            $this->error('Erro ao salvar o usuário: ' . $e->getMessage(), position: 'toast-top');
            logger()->error('Erro ao salvar o usuário: ' . $e->getMessage());
        }
    }

    public function addEvolution()
    {
        $currentUserType = auth()->user()->type_user;

        if ($currentUserType === UserType::User->value) {
            $this->openEvolutionModal();
            return;
        }

        // Caso contrário, adiciona normalmente
        $this->form->addEvolution();
    }

    public function removeEvolution($index)
    {
        $this->form->removeEvolution($index);
    }

    public function removeZohoIntegration($index)
    {
        unset($this->expandedZohoIntegrations[$index]);
        $this->form->removeZohoIntegration($index);
    }

    public function addEmailIntegration()
    {
        $this->form->addEmailIntegration();
    }

    public function removeEmailIntegration($index)
    {
        $this->form->removeEmailIntegration($index);
    }

    public function getVersionsProperty()
    {
        return Versions::all()->map(function ($version) {
            return [
                'id' => $version->id,
                'name' => $version->name,
            ];
        })->toArray();
    }

    public function getPlansProperty()
    {
        return Plan::where('active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($plan) {
                $priceDisplay = $plan->price ? number_format($plan->price, 2, ',', '.') : 'Sob Consulta';
                return [
                    'id' => $plan->id,
                    'name' => "{$plan->name} (R$ {$priceDisplay})",
                ];
            })
            ->prepend(['id' => '', 'name' => 'Selecione um Plano...'])
            ->values()
            ->toArray();
    }

    public function getUserTypesProperty()
    {
        return collect(UserType::cases())
            ->map(function ($case) {
                return [
                    'id' => $case->value,
                    'name' => ucfirst(str_replace('_', ' ', $case->name)),
                ];
            })
            ->prepend(['id' => '', 'name' => 'Selecione...'])
            ->values()
            ->toArray();
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'name', 'label' => 'Nome', 'class' => 'text-left'],
            ['key' => 'email', 'label' => 'Email', 'class' => 'text-left'],
            ['key' => 'role', 'label' => 'Papel', 'class' => 'text-left'],
        ];

        $agents = $this->editMode && $this->userId
            ? ChatwootsAgents::where('user_id', $this->userId)
            ->paginate($this->perPage)
            ->through(function ($agent) {
                return [
                    'id' => $agent->agent_id,
                    'name' => $agent->name,
                    'email' => $agent->email ? $agent->email : 'N/A',
                    'role' => $agent->role,
                ];
            })
            : [];

        return view('livewire.user-config-index', [
            'userTypes' => $this->userTypes,
            'headers' => $headers,
            'title' => $this->title,
            'agents' => $agents,
            'perPage' => $this->perPage,
            'versions' => $this->versions,
            'options' => $this->getUserTypesProperty(),
            'plans' => $this->plans,
        ]);
    }
}