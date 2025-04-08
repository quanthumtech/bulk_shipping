<?php

namespace App\Livewire\Forms;

use App\Models\User;
use App\Models\Versions;
use App\Services\ChatwootService;
use Faker\Core\Version;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Validate;
use Livewire\Form;

class VersionForm extends Form
{
    #[Validate('required')]
    public $version = '';

    protected $chatwootService;

    public function store()
    {
        $this->validate();

        // Desativa todas as versões
        Versions::query()->update(['active' => false]);

        // Ativa ou cria a versão selecionada
        $version = Versions::where('id', $this->version)->first();
        if ($version) {
            $version->active = true;
            $version->save();
        } else {
            $version = Versions::create([
                'name'   => $this->version,
                'active' => true
            ]);
        }

        // Define a nova base da URL de acordo com a versão
        if ($version->name === 'Evolution v1') {
            $urlBaseV1 = Versions::where('id', 1)->first();
            $newBaseUrl = $urlBaseV1->url_evolution;
        } elseif ($version->name === 'Evolution v2') {
            $urlBaseV2 = Versions::where('id', 2)->first();
            $newBaseUrl = $urlBaseV2->url_evolution;
        } else {
            Log::error('Versão inválida: ' . $version->name);
            return;
        }

        // Atualiza o campo api_post no modelo Users mantendo a parte da instância
        // Supondo que cada usuário tem na api_post uma URL que termina com a sua instância,
        // por exemplo: "https://.../message/sendText/SeneDevops"
        $users = User::all();
        foreach ($users as $user) {
            // Separa a URL atual para extrair o identificador da instância
            $parts    = explode('/', rtrim($user->api_post, '/'));
            $instance = end($parts);
            $user->api_post = rtrim($newBaseUrl, '/') . '/' . $instance;
            $user->save();

            Log::info('URL atualizada para o usuário ' . $user->name . ': ' . $user->api_post);
        }
    }
}
