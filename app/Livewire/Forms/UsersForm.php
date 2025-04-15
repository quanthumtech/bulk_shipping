<?php
namespace App\Livewire\Forms;

use App\Models\ChatwootsAgents;
use App\Models\User;
use App\Models\Versions;
use App\Models\Evolution;
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

    // Getter para carregar as versões disponíveis
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
            // Extrai a parte após 'sendText' do api_post
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

        if (empty($this->evolutions)) {
            $this->evolutions = [['version_id' => '', 'apikey' => '', 'api_post' => '', 'active' => true]];
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
}
