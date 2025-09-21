<?php

namespace App\Livewire\Forms;

use App\Models\ChatwootsAgents;
use App\Models\User;
use App\Models\Versions;
use App\Models\Evolution;
use App\Models\ZohoIntegration;
use App\Models\EmailIntegration; // Adicione esta importação
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

    #[Validate('string', 'required')]
    public $name;

    #[Validate('string', 'required')]
    public $email;

    #[Validate('string', 'required')]
    public $password;

    public $chatwoot_accoumts;
    public $active;
    public $type_user;
    public $token_acess;

    public array $evolutions = [['version_id' => '', 'apikey' => '', 'api_post' => '', 'active' => true]];
    public array $zoho_integrations = [['client_id' => '', 'client_secret' => '', 'refresh_token' => '', 'code' => '', 'active' => true]];
    public array $email_integrations = [['host' => '', 'port' => '', 'username' => '', 'password' => '', 'encryption' => '', 'from_email' => '', 'from_name' => '', 'active' => true]];

    public function getVersionsProperty()
    {
        return Versions::all()->map(function ($version) {
            return [
                'id' => $version->id,
                'name' => $version->name,
            ];
        })->toArray();
    }

    public function setUsers(User $users)
    {
        $this->users = $users;
        $this->name = $users->name;
        $this->email = $users->email;
        $this->chatwoot_accoumts = $users->chatwoot_accoumts;
        $this->active = (bool) $users->active;
        $this->type_user = $users->type_user;
        $this->token_acess = $users->token_acess;
        $this->password = '';

        $this->evolutions = $users->evolutions->map(function ($evolution) {
            $apiPost = $evolution->api_post;
            if (strpos($evolution->api_post, 'sendText') !== false) {
                $apiPost = substr($evolution->api_post, strpos($evolution->api_post, 'sendText') + strlen('sendText'));
                $apiPost = ltrim($apiPost, '/');
            }
            return [
                'id' => $evolution->id,
                'version_id' => $evolution->version_id,
                'apikey' => $evolution->apikey,
                'api_post' => $apiPost,
                'active' => (bool) $evolution->active,
            ];
        })->toArray();

        $this->zoho_integrations = $users->zohoIntegrations->map(function ($zoho) {
            return [
                'id' => $zoho->id,
                'client_id' => $zoho->client_id,
                'client_secret' => $zoho->client_secret,
                'refresh_token' => $zoho->refresh_token,
                'code' => '',
                'active' => true,
            ];
        })->toArray();

        $this->email_integrations = $users->emailIntegrations->map(function ($email) {
            return [
                'id' => $email->id,
                'host' => $email->host,
                'port' => $email->port,
                'username' => $email->username,
                'password' => $email->password,
                'encryption' => $email->encryption,
                'from_email' => $email->from_email,
                'from_name' => $email->from_name,
                'active' => (bool) $email->active,
            ];
        })->toArray();

        if (empty($this->evolutions)) {
            $this->evolutions = [['version_id' => '', 'apikey' => '', 'api_post' => '', 'active' => true]];
        }

        if (empty($this->zoho_integrations)) {
            $this->zoho_integrations = [['client_id' => '', 'client_secret' => '', 'refresh_token' => '', 'code' => '', 'active' => true]];
        }

        if (empty($this->email_integrations)) {
            $this->email_integrations = [['host' => '', 'port' => '', 'username' => '', 'password' => '', 'encryption' => '', 'from_email' => '', 'from_name' => '', 'active' => true]];
        }
    }

    public function store()
    {
        $this->validate([
            'name' => 'required|string',
            'email' => 'required|string',
            'password' => 'required|string',
            'evolutions.*.version_id' => 'required|exists:versions,id',
            'evolutions.*.apikey' => 'required|string',
            'evolutions.*.api_post' => 'required|string',
            'zoho_integrations.*.client_id' => 'nullable|string',
            'zoho_integrations.*.client_secret' => 'nullable|string',
            'zoho_integrations.*.refresh_token' => 'nullable|string',
            'zoho_integrations.*.code' => 'nullable|string',
            'email_integrations.*.host' => 'required|string',
            'email_integrations.*.port' => 'required|integer',
            'email_integrations.*.username' => 'required|string',
            'email_integrations.*.password' => 'required|string',
            'email_integrations.*.encryption' => 'nullable|string|in:none,tls,ssl',
            'email_integrations.*.from_email' => 'required|email',
            'email_integrations.*.from_name' => 'required|string',
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'chatwoot_accoumts' => $this->chatwoot_accoumts,
            'active' => $this->active,
            'type_user' => $this->type_user,
            'token_acess' => $this->token_acess,
            'password' => Hash::make($this->password),
        ]);

        foreach ($this->evolutions as $evolution) {
            if (!empty($evolution['version_id']) && !empty($evolution['apikey']) && !empty($evolution['api_post'])) {
                $version = Versions::find($evolution['version_id']);
                if (!$version) {
                    $this->error('Versão inválida selecionada.', position: 'toast-top');
                    return;
                }
                $completeApiPost = $version->url_evolution . $evolution['api_post'];
                Evolution::create([
                    'user_id' => $user->id,
                    'version_id' => $evolution['version_id'],
                    'apikey' => $evolution['apikey'],
                    'api_post' => $completeApiPost,
                    'active' => $evolution['active'],
                ]);
            }
        }

        foreach ($this->zoho_integrations as $zoho) {
            if (!empty($zoho['client_id']) && !empty($zoho['client_secret'])) {
                ZohoIntegration::create([
                    'user_id' => $user->id,
                    'client_id' => $zoho['client_id'],
                    'client_secret' => $zoho['client_secret'],
                    'refresh_token' => $zoho['refresh_token'],
                ]);
            }
        }

        foreach ($this->email_integrations as $email) {
            if (!empty($email['host']) && !empty($email['port']) && !empty($email['username']) && !empty($email['password']) && !empty($email['from_email']) && !empty($email['from_name'])) {
                EmailIntegration::create([
                    'user_id' => $user->id,
                    'host' => $email['host'],
                    'port' => $email['port'],
                    'username' => $email['username'],
                    'password' => $email['password'], // Considere criptografar isso em produção
                    'encryption' => $email['encryption'],
                    'from_email' => $email['from_email'],
                    'from_name' => $email['from_name'],
                    'active' => $email['active'],
                ]);
            }
        }

        if ($this->chatwoot_accoumts && $this->token_acess) {
            $chatwootService = new ChatwootService();
            $agents = $chatwootService->getAgents($this->chatwoot_accoumts, $this->token_acess);
            foreach ($agents as $agent) {
                ChatwootsAgents::create([
                    'user_id' => $user->id,
                    'chatwoot_account_id' => $this->chatwoot_accoumts,
                    'agent_id' => $agent['agent_id'],
                    'name' => $agent['name'],
                    'email' => $agent['email'],
                    'role' => $agent['role'],
                ]);
            }
        }

        $this->reset();
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|string',
            'email' => 'required|string',
            'password' => 'nullable|string',
            'evolutions.*.version_id' => 'required|exists:versions,id',
            'evolutions.*.apikey' => 'required|string',
            'evolutions.*.api_post' => 'required|string',
            'zoho_integrations.*.client_id' => 'nullable|string',
            'zoho_integrations.*.client_secret' => 'nullable|string',
            'zoho_integrations.*.refresh_token' => 'nullable|string',
            'zoho_integrations.*.code' => 'nullable|string',
            'email_integrations.*.host' => 'required|string',
            'email_integrations.*.port' => 'required|integer',
            'email_integrations.*.username' => 'required|string',
            'email_integrations.*.password' => 'required|string',
            'email_integrations.*.encryption' => 'nullable|string|in:none,tls,ssl',
            'email_integrations.*.from_email' => 'required|email',
            'email_integrations.*.from_name' => 'required|string',
        ]);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'chatwoot_accoumts' => $this->chatwoot_accoumts,
            'active' => $this->active,
            'type_user' => $this->type_user,
            'token_acess' => $this->token_acess,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        $this->users->update($data);

        Evolution::where('user_id', $this->users->id)->delete();
        foreach ($this->evolutions as $evolution) {
            if (!empty($evolution['version_id']) && !empty($evolution['apikey']) && !empty($evolution['api_post'])) {
                $version = Versions::find($evolution['version_id']);
                if (!$version) {
                    $this->error('Versão inválida selecionada.', position: 'toast-top');
                    return;
                }
                $completeApiPost = $version->url_evolution . $evolution['api_post'];
                Evolution::create([
                    'user_id' => $this->users->id,
                    'version_id' => $evolution['version_id'],
                    'apikey' => $evolution['apikey'],
                    'api_post' => $completeApiPost,
                    'active' => $evolution['active'],
                ]);
            }
        }

        ZohoIntegration::where('user_id', $this->users->id)->delete();
        foreach ($this->zoho_integrations as $zoho) {
            if (!empty($zoho['client_id']) && !empty($zoho['client_secret'])) {
                ZohoIntegration::create([
                    'user_id' => $this->users->id,
                    'client_id' => $zoho['client_id'],
                    'client_secret' => $zoho['client_secret'],
                    'refresh_token' => $zoho['refresh_token'],
                ]);
            }
        }

        EmailIntegration::where('user_id', $this->users->id)->delete();
        foreach ($this->email_integrations as $email) {
            if (!empty($email['host']) && !empty($email['port']) && !empty($email['username']) && !empty($email['password']) && !empty($email['from_email']) && !empty($email['from_name'])) {
                EmailIntegration::create([
                    'user_id' => $this->users->id,
                    'host' => $email['host'],
                    'port' => $email['port'],
                    'username' => $email['username'],
                    'password' => $email['password'], // Considere criptografar isso em produção
                    'encryption' => $email['encryption'],
                    'from_email' => $email['from_email'],
                    'from_name' => $email['from_name'],
                    'active' => $email['active'],
                ]);
            }
        }

        if ($this->chatwoot_accoumts && $this->token_acess) {
            $chatwootService = new ChatwootService();
            $agents = $chatwootService->getAgents($this->chatwoot_accoumts, $this->token_acess);
            ChatwootsAgents::where('user_id', $this->users->id)->delete();
            foreach ($agents as $agent) {
                ChatwootsAgents::create([
                    'user_id' => $this->users->id,
                    'chatwoot_account_id' => $this->chatwoot_accoumts,
                    'agent_id' => $agent['agent_id'],
                    'name' => $agent['name'],
                    'email' => $agent['email'],
                    'role' => $agent['role'],
                ]);
            }
        }

        $this->reset();
    }

    public function addEvolution()
    {
        $this->evolutions[] = ['version_id' => '', 'apikey' => '', 'api_post' => '', 'active' => true];
    }

    public function removeEvolution($index)
    {
        unset($this->evolutions[$index]);
        $this->evolutions = array_values($this->evolutions);
    }

    public function addZohoIntegration()
    {
        $this->zoho_integrations[] = ['client_id' => '', 'client_secret' => '', 'refresh_token' => '', 'code' => '', 'active' => true];
    }

    public function removeZohoIntegration($index)
    {
        unset($this->zoho_integrations[$index]);
        $this->zoho_integrations = array_values($this->zoho_integrations);
    }

    public function addEmailIntegration()
    {
        $this->email_integrations[] = ['host' => '', 'port' => '', 'username' => '', 'password' => '', 'encryption' => '', 'from_email' => '', 'from_name' => '', 'active' => true];
    }

    public function removeEmailIntegration($index)
    {
        unset($this->email_integrations[$index]);
        $this->email_integrations = array_values($this->email_integrations);
    }
}
