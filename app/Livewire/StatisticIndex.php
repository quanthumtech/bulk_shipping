<?php

namespace App\Livewire;

use App\Models\GroupSend;
use App\Models\Send;
use App\Services\ChatwootService;
use Livewire\Component;

class StatisticIndex extends Component
{
    public $contatos;

    // Dados dos gráficos
    public array $contatosChart = [];
    public array $mensagensChart = [];

    protected $chatwootService;

    public function mount(ChatwootService $chatwootService)
    {
        $this->chatwootService = $chatwootService;
        $this->contatos = $this->chatwootService->getContatos();

        $qtd_grupos = GroupSend::where('active', 1)->count();
        $grupos_ativos = GroupSend::where('active', 1)->get();
        $total_contatos_por_grupos = $grupos_ativos->reduce(function ($carry, $grupo) {
            $contatos = json_decode($grupo->phone_number, true);
            return $carry + (is_array($contatos) ? count($contatos) : 0);
        }, 0);

        $media_contatos_por_grupo = $qtd_grupos > 0
            ? $total_contatos_por_grupos / $qtd_grupos
            : 0;

        $contatosPorDia = GroupSend::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn($item) => [$item->date => $item->count])
            ->toArray();

        $frequenciaMensagens = Send::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn($item) => [$item->date => $item->count])
            ->toArray();

        $this->contatosChart = [
            'type' => 'doughnut',
            'data' => [
                'labels' => array_keys($contatosPorDia),
                'datasets' => [
                    [
                        'label' => 'Contatos',
                        'data' => array_values($contatosPorDia),
                        'borderColor' => '#4A90E2',
                        'backgroundColor' => 'rgba(74, 144, 226, 0.2)',
                    ]
                ],
            ],
        ];

        $this->mensagensChart = [
            'type' => 'polarArea',
            'data' => [
                'labels' => array_keys($frequenciaMensagens),
                'datasets' => [
                    [
                        'label' => 'Mensagens',
                        'data' => array_values($frequenciaMensagens),
                        'backgroundColor' => '#65C3C8',
                    ]
                ],
            ],
        ];

        $this->dispatch('chartDataUpdated');
    }

    public function render()
    {
        $qtd_contatos = count($this->contatos);
        $qtd_msgs = Send::where('active', 1)->count();

        return view('livewire.statistic-index', [
            'contatos'       => $qtd_contatos,
            'menssages'      => $qtd_msgs,
            'grupos'         => GroupSend::where('active', 1)->count(),
            'media_contatos' => $this->mediaContatosPorGrupo(),
            'contatosChart'  => $this->contatosChart,
            'mensagensChart' => $this->mensagensChart,
        ]);
    }

    private function mediaContatosPorGrupo()
    {
        $qtd_grupos = GroupSend::where('active', 1)->count();
        $grupos_ativos = GroupSend::where('active', 1)->get();
        $total_contatos_por_grupos = $grupos_ativos->reduce(function ($carry, $grupo) {
            $contatos = json_decode($grupo->phone_number, true);
            return $carry + (is_array($contatos) ? count($contatos) : 0);
        }, 0);

        return $qtd_grupos > 0 ? $total_contatos_por_grupos / $qtd_grupos : 0;
    }
}
