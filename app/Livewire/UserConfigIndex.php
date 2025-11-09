<?php

namespace App\Livewire;

use App\Enums\UserType;
use App\Livewire\Forms\UsersForm;
use App\Models\ChatwootsAgents;
use App\Models\Plan;
use App\Models\User;
use App\Models\Versions;
use App\Models\ZohoIntegration;
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

    public function mount($userId = null, $code = null, $zoho_index = null)
    {
        $this->userId = $userId;
        $this->zoho_code = $code;
        $this->zoho_integration_index = $zoho_index;

        if ($this->userId) {
            $user = User::find($this->userId);
            if ($user) {
                $this->form->setUsers($user);
                $this->form->plan_id = $user->plan_id ?? '';
                $this->editMode = true;
                $this->title = 'Editar Usuário';
            } else {
                $this->error('Usuário não encontrado.', position: 'toast-top');
                return redirect()->route('users.index');
            }
        }

        if ($this->zoho_code && $this->zoho_integration_index !== null) {
            $this->handleZohoCallback();
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
        $this->form->addEvolution();
    }

    public function removeEvolution($index)
    {
        $this->form->removeEvolution($index);
    }

    public function addZohoIntegration()
    {
        $this->form->addZohoIntegration();
    }

    public function removeZohoIntegration($index)
    {
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
                    'email' => $agent->email ?? 'N/A',
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
