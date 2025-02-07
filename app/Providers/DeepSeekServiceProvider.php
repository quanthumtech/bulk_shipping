<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DeepSeekService
{
    public function generateMessage($prompt)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.deepseek.key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.deepseek.com/v1/chat/completions', [
            'model' => 'deepseek-chat',
            'messages' => [[
                'role' => 'user',
                'content' => $prompt
            ]]
        ]);
    }
}
