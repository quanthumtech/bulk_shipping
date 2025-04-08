<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class FaqIndex extends Component
{
    public array $show = [
        'gerenciar_mensagens' => false,
        'cadencia' => false,
        // Adicione mais módulos aqui conforme necessário
    ];

    public function toggleCollapse($key)
    {
        if (array_key_exists($key, $this->show)) {
            $this->show[$key] = !$this->show[$key];
            Log::info("Collapse '{$key}' toggled: " . ($this->show[$key] ? 'Aberto' : 'Fechado'));
        } else {
            Log::warning("Chave '{$key}' não encontrada no array show.");
        }
    }

    public function render()
    {
        return view('livewire.faq-index');
    }
}
