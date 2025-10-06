<?php

namespace App\Livewire;

use App\Enums\UserType;
use App\Models\CadenceMessage;
use App\Models\Cadencias;
use App\Models\GroupSend;
use App\Models\ListContatos;
use App\Models\Send;
use App\Models\SyncFlowLeads;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StatisticIndex extends Component
{
    public $selectedUserId;
    public $startDate;
    public $endDate;
    public $selectedStatus;
    public $users;
    public $leadStatuses = ['Atribuído', 'Em Progresso', 'Finalizado'];
    public $leadsAtivos;
    public $gruposAtivos;
    public $contatosTotais;
    public $cadenciasAtivas;
    public $atribuidos;
    public $semCadencia;
    public $emProgresso;
    public $finalizados;

    // Adicionado: rates para os progress radials
    public $emailRate = 0;
    public $whatsappRate = 0;

    public array $leadsChart = [];
    public array $frequenciaChart = [];
    // Removido: falhaChart (não precisa mais)

    public function mount()
    {
        $this->selectedUserId = Auth::user()->type_user === UserType::Admin->value || Auth::user()->type_user === UserType::SuperAdmin->value ? null : Auth::id();
        $this->users = User::where('active', true)->get(['id', 'name']);
        $this->startDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');
        $this->selectedStatus = null;

        // Adicionado: abre o collapse por padrão para admins
        $isAdmin = Auth::user()->type_user === UserType::Admin->value || Auth::user()->type_user === UserType::SuperAdmin->value;
        $this->show['open_filtro'] = $isAdmin;

        $this->loadData();
    }

    public array $show = [
        'open_filtro' => false
    ];

    public function toggleCollapse($key)
    {
        if (array_key_exists($key, $this->show)) {
            $this->show[$key] = !$this->show[$key];
        }
    }

    public function updatedSelectedUserId()
    {
        $this->loadData();
    }

    public function updatedStartDate()
    {
        $this->loadData();
    }

    public function updatedEndDate()
    {
        $this->loadData();
    }

    public function updatedSelectedStatus()
    {
        $this->loadData();
    }

    private function loadData()
    {
        $userId = $this->selectedUserId;
        $startDate = $this->startDate;
        $endDate = $this->endDate;
        $status = $this->selectedStatus;

        $dateRange = Carbon::parse($startDate)->startOfDay()->toDateTimeString() . ' TO ' . Carbon::parse($endDate)->endOfDay()->toDateTimeString();

        $leadsQuery = SyncFlowLeads::query()->whereBetween('created_at', [$startDate, $endDate]);
        if ($userId) {
            $leadsQuery->whereHas('cadencia', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }
        if ($status) {
            $leadsQuery->where('situacao_contato', $status);
        }

        $this->leadsAtivos = $leadsQuery->clone()
            ->whereIn('situacao_contato', ['Atribuído', 'Em Progresso'])
            ->count();

        $this->atribuidos = $leadsQuery->clone()
            ->where('situacao_contato', '=', 'Atribuído')
            ->count();

        $semCadenciaQuery = SyncFlowLeads::query()->whereBetween('created_at', [$startDate, $endDate])->whereNull('cadencia_id');
        
        if ($status) {
            $semCadenciaQuery->where('situacao_contato', $status);
        }
        $this->semCadencia = $semCadenciaQuery->count();

        $this->emProgresso = $leadsQuery->clone()
            ->whereNotNull('cadencia_id')
            ->whereRaw('JSON_LENGTH(COALESCE(completed_cadences, "[]")) = 0')
            ->count();

        $this->finalizados = $leadsQuery->clone()
            ->whereRaw('JSON_LENGTH(COALESCE(completed_cadences, "[]")) > 0')
            ->count();

        $gruposQuery = GroupSend::where('active', 1)->whereBetween('created_at', [$startDate, $endDate]);
        if ($userId) {
            $gruposQuery->where('user_id', $userId);
        }
        $this->gruposAtivos = $gruposQuery->count();

        $contatosQuery = ListContatos::whereBetween('created_at', [$startDate, $endDate]);
        $this->contatosTotais = $contatosQuery->count();

        $cadenciasQuery = Cadencias::where('active', true)->whereBetween('created_at', [$startDate, $endDate]);
        if ($userId) {
            $cadenciasQuery->where('user_id', $userId);
        }
        $this->cadenciasAtivas = $cadenciasQuery->count();

        $this->leadsChart = [
            'type' => 'bar',
            'data' => [
                'labels' => ['Atribuídos', 'Sem Cadência', 'Em Progresso', 'Finalizados'],
                'datasets' => [
                    [
                        'label' => 'Leads',
                        'data' => [$this->atribuidos, $this->semCadencia, $this->emProgresso, $this->finalizados],
                        'backgroundColor' => ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'],
                        'borderColor' => ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'],
                        'borderWidth' => 1,
                    ]
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ],
        ];

        $frequenciaQuery = CadenceMessage::whereNotNull('enviado_em')
            ->whereBetween('enviado_em', [$startDate, $endDate]);
        if ($userId) {
            $frequenciaQuery->whereHas('syncFlowLead.cadencia', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }
        $frequenciaData = $frequenciaQuery
            ->selectRaw('DATE(enviado_em) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->mapWithKeys(fn($item) => [$item->date => $item->count])
            ->toArray();

        $this->frequenciaChart = [
            'type' => 'line',
            'data' => [
                'labels' => array_keys($frequenciaData),
                'datasets' => [
                    [
                        'label' => 'Mensagens Enviadas',
                        'data' => array_values($frequenciaData),
                        'borderColor' => '#4A90E2',
                        'backgroundColor' => 'rgba(74, 144, 226, 0.1)',
                        'tension' => 0.1,
                        'fill' => true,
                    ]
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => 'Data'
                        ]
                    ]
                ],
            ],
        ];

        // Mantido: query para sends, mas agora setando as rates públicas
        $sendQuery = Send::whereNotNull('sent_at')->whereBetween('sent_at', [$startDate, $endDate]);
        if ($userId) {
            $sendQuery->where('user_id', $userId);
        }

        $emailTotal = $sendQuery->clone()->whereJsonLength('emails', '>', 0)->count();
        $emailFailed = $sendQuery->clone()->whereJsonLength('emails', '>', 0)->where('status', 'failed')->count();
        $this->emailRate = $emailTotal > 0 ? round(($emailFailed / $emailTotal) * 100, 2) : 0;

        $whatsappTotal = $sendQuery->clone()->whereNotNull('phone_number')->count();
        $whatsappFailed = $sendQuery->clone()->whereNotNull('phone_number')->where('status', 'failed')->count();
        $this->whatsappRate = $whatsappTotal > 0 ? round(($whatsappFailed / $whatsappTotal) * 100, 2) : 0;

        // Removido: falhaChart

        $this->dispatch('chartDataUpdated');
    }

    public function render()
    {
        $isAdmin = Auth::user()->type_user === UserType::Admin->value || Auth::user()->type_user === UserType::SuperAdmin->value;

        return view('livewire.statistic-index', [
            'leadsAtivos' => $this->leadsAtivos,
            'gruposAtivos' => $this->gruposAtivos,
            'contatosTotais' => $this->contatosTotais,
            'cadenciasAtivas' => $this->cadenciasAtivas,
            'leadsChart' => $this->leadsChart,
            'frequenciaChart' => $this->frequenciaChart,
            // Removido: falhaChart
            // Adicionado: rates para o view
            'emailRate' => $this->emailRate,
            'whatsappRate' => $this->whatsappRate,
            'isAdmin' => $isAdmin,
        ]);
    }
}