<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'quanthum@plataformamundo.com.br'],
            [
                'name' => 'Quanthum Sistemas',
                'password' => Hash::make('Mundo@2025'),
                'type_user' => '1',
                'active' => true,
            ]
        );
    }
}