<?php

declare( strict_types=1 );

namespace Database\Seeders;

use App\Models\Evolution;
use App\Models\User;
use App\Models\Versions;
use Illuminate\Database\Seeder;

class EvolutionSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'quanthum@plataformamundo.com.br')->first();
        $version = Versions::where('name', 'Evolution v2.1')->first();

        if ($user && $version) {
            Evolution::firstOrCreate([
                'user_id' => $user->id,
                'version_id' => $version->id,
                'apikey' => '3B1D0A2D9BDA-4D10-863D-E982CD0CD4CC',
                'api_post' => 'https://evolution.plataformamundo.com.br/message/sendText/IsaqueDev',
                'active' => true,
            ]);
        }
    }
}