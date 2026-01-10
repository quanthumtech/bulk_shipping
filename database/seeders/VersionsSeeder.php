<?php

declare( strict_types=1 );

namespace Database\Seeders;

use App\Models\Versions;
use Illuminate\Database\Seeder;

class VersionsSeeder extends Seeder
{
    public function run(): void
    {
        Versions::firstOrCreate([
            'name' => 'Evolution v2.1',
            'url_evolution' => 'https://evolution.plataformamundo.com.br/message/sendText/',
            'type' => 'v2',
            'active' => true,
        ]);
    }
}