<?php

namespace App\Livewire\Forms;

use App\Models\ChatwootsAgents;
use App\Models\User;
use App\Models\Versions;
use App\Services\ChatwootService;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Livewire\WithFileUploads;
use Mary\Traits\WithMediaSync;
use Illuminate\Support\Collection;
use Mary\Traits\Toast;

class UsersForm extends Form
{
    use WithFileUploads, WithMediaSync, Toast;

    public ?User $users = null;

    #[validate('string', 'required')]
    public $name;

    #[validate('string', 'required')]
    public $email;

    #[validate('string', 'required')]
    public $password;

    public $chatwoot_accoumts;

    public $active;

    public $type_user;

    public $token_acess;

    public $apikey;

    public $api_post;

    public function setUsers(User $users)
    {
        $this->users             = $users;
        $this->name              = $users->name;
        $this->email             = $users->email;
        $this->chatwoot_accoumts = $users->chatwoot_accoumts;
        $this->active            = (bool) $users->active;
        $this->type_user         = $users->type_user;
        $this->token_acess       = $users->token_acess;
        $this->apikey            = $users->apikey;
        $this->api_post          = substr($users->api_post, strrpos($users->api_post, '/') + 1);
        $this->password          = '';
    }

    public function store()
    {
        $this->validate();

        $versionAtiva = Versions::where('active', 1)->first();

        // Concatena a URL da versão ativa
        $completeApiPost = (string)$versionAtiva->url_evolution . $this->api_post;

        // Cria o usuário
        $user = User::create([
            'name'              => $this->name,
            'email'             => $this->email,
            'chatwoot_accoumts' => $this->chatwoot_accoumts,
            'active'            => $this->active,
            'type_user'         => $this->type_user,
            'token_acess'       => $this->token_acess,
            'apikey'            => $this->apikey,
            'api_post'          => $completeApiPost,
            'password'          => Hash::make($this->password),
        ]);

        // Busca e armazena os agentes
        if ($this->chatwoot_accoumts && $this->token_acess) {
            $chatwootService = new ChatwootService();
            $agents = $chatwootService->getAgents($this->chatwoot_accoumts, $this->token_acess);

            foreach ($agents as $agent) {
                ChatwootsAgents::create([
                    'user_id'            => $user->id,
                    'chatwoot_account_id' => $this->chatwoot_accoumts,
                    'agent_id'           => $agent['agent_id'],
                    'name'               => $agent['name'],
                    'email'              => $agent['email'],
                    'role'               => $agent['role'],
                ]);
            }
        }

        $this->reset();
    }

    public function update()
    {
        $this->validate();

        $versionAtiva = Versions::where('active', 1)->first();

        // Concatena a URL da versão ativa
        $completeApiPost = (string)$versionAtiva->url_evolution . $this->api_post;

        $data = [
            'name'              => $this->name,
            'email'             => $this->email,
            'chatwoot_accoumts' => $this->chatwoot_accoumts,
            'active'            => $this->active,
            'type_user'         => $this->type_user,
            'token_acess'       => $this->token_acess,
            'apikey'            => $this->apikey,
            'api_post'          => $completeApiPost,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        $this->users->update($data);

        // Atualiza os agentes
        if ($this->chatwoot_accoumts && $this->token_acess) {
            $chatwootService = new ChatwootService();
            $agents = $chatwootService->getAgents($this->chatwoot_accoumts, $this->token_acess);

            // Remove agentes antigos
            ChatwootsAgents::where('user_id', $this->users->id)->delete();

            // Adiciona os novos agentes
            foreach ($agents as $agent) {
                ChatwootsAgents::create([
                    'user_id'            => $this->users->id,
                    'chatwoot_account_id' => $this->chatwoot_accoumts,
                    'agent_id'           => $agent['agent_id'],
                    'name'               => $agent['name'],
                    'email'              => $agent['email'],
                    'role'               => $agent['role'],
                ]);
            }
        }

        $this->reset();
    }

}
