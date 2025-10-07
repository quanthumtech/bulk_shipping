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
use App\Services\ChatwootService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    // Rates para falha e sucesso
    public $emailRate = 0;
    public $whatsappRate = 0;
    public $emailSuccessRate = 100;
    public $whatsappSuccessRate = 100;

    // Para cards e tabela
    public $ultimosLeads;
    public $ultimosContatos;
    public $cadencias;
    public array $headersCadencias = [
        ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
        ['key' => 'name', 'label' => 'Nome'],
        ['key' => 'leads_count', 'label' => 'Leads Atribuídos'],
        ['key' => 'active_name', 'label' => 'Status'],
        ['key' => 'formatted_created_at', 'label' => 'Criado'],
    ];

    public array $leadsChart = [];
    public array $frequenciaChart = [];

    public function mount()
    {
        // Sempre carrega dados do usuário atual inicialmente
        $this->selectedUserId = Auth::id(); // Default: dados do usuário logado
        $this->users = User::where('active', true)->get(['id', 'name']);
        $this->startDate = Carbon::now()->subYear()->format('Y-m-d'); // Um ano atrás
        $this->endDate = Carbon::now()->format('Y-m-d');
        $this->selectedStatus = null;

        // Para admins, permite filtrar por outros, mas inicia com o próprio
        $isAdmin = Auth::user()->type_user === UserType::Admin->value || Auth::user()->type_user === UserType::SuperAdmin->value;
        if ($isAdmin) {
            $this->selectedUserId = null; // Admins iniciam sem filtro (todos), mas podem selecionar
        }
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

    // NOVO: Método para botão de filtro (chama loadData explicitamente)
    public function applyFilters()
    {
        $this->loadData();
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

    public function syncContatosDashboard()
    {
        $chatwootService = app(ChatwootService::class);

        try {
            $totalSync = $chatwootService->syncContatos();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Sincronização concluída! {$totalSync} contatos processados.",
                'position' => 'toast-top'
            ]);
            $this->loadData();
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro na sincronização: ' . $e->getMessage(),
                'position' => 'toast-top'
            ]);
        }
    }

    private function loadData()
    {
        $userId = $this->selectedUserId;
        $startDate = $this->startDate;
        $endDate = $this->endDate;
        $status = $this->selectedStatus;

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
        // REMOVIDO: Filtro por userId/lead.cadencia - agora só últimos da lista, sem relação com leads
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

        $sendQuery = Send::whereNotNull('sent_at')->whereBetween('sent_at', [$startDate, $endDate]);
        if ($userId) {
            $sendQuery->where('user_id', $userId);
        }

        $emailTotal = $sendQuery->clone()->whereJsonLength('emails', '>', 0)->count();
        $emailFailed = $sendQuery->clone()->whereJsonLength('emails', '>', 0)->where('status', 'failed')->count();
        $this->emailRate = $emailTotal > 0 ? round(($emailFailed / $emailTotal) * 100, 2) : 0;
        $this->emailSuccessRate = 100 - $this->emailRate;

        $whatsappTotal = $sendQuery->clone()->whereNotNull('phone_number')->count();
        $whatsappFailed = $sendQuery->clone()->whereNotNull('phone_number')->where('status', 'failed')->count();
        $this->whatsappRate = $whatsappTotal > 0 ? round(($whatsappFailed / $whatsappTotal) * 100, 2) : 0;
        $this->whatsappSuccessRate = 100 - $this->whatsappRate;

        // Últimos 3 Leads
        $leadsQuery = SyncFlowLeads::query()->whereBetween('created_at', [$startDate, $endDate]);
        if ($userId) {
            $leadsQuery->whereHas('cadencia', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }
        if ($status) {
            $leadsQuery->where('situacao_contato', $status);
        }
        $this->ultimosLeads = $leadsQuery->latest()->limit(3)->get()->map(function ($lead) {
            $lead->formatted_created_at = Carbon::parse($lead->created_at)->format('d/m/Y');
            return $lead;
        });

        // Últimos 3 Contatos (simples: últimos da lista, sem filtro de user/lead.cadencia)
        $contatosQuery = ListContatos::whereBetween('created_at', [$startDate, $endDate])->latest()->limit(3);
        $this->ultimosContatos = $contatosQuery->get()->map(function ($contato) {
            $contato->sync_info = $contato->chatwoot_id ? 'Sync Chatwoot (ID: ' . $contato->chatwoot_id . ')' : 'Sem Sync';
            $contato->formatted_created_at = Carbon::parse($contato->created_at)->format('d/m/Y');
            return $contato;
        });

        // Cadências com count leads
        $cadenciasQuery = Cadencias::where('active', true)->whereBetween('created_at', [$startDate, $endDate]);
        $bindings = [true, $startDate, $endDate];

        if ($userId) {
            $cadenciasQuery->where('user_id', $userId);
            $bindings[] = $userId;
        }

        $this->cadencias = $cadenciasQuery->select('cadencias.*', DB::raw('(SELECT COUNT(*) FROM sync_flow_leads WHERE cadencia_id = cadencias.id AND created_at BETWEEN ? AND ?) as leads_count'))
            ->setBindings(array_merge($bindings, [$startDate, $endDate]))
            ->limit(10)
            ->get()
            ->map(function ($cadencia) {
                $cadencia->formatted_created_at = Carbon::parse($cadencia->created_at)->format('d/m/Y');
                $cadencia->active_name = $cadencia->active ? 'Ativo' : 'Inativo';
                return $cadencia;
            });

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
            'emailSuccessRate' => $this->emailSuccessRate,
            'whatsappSuccessRate' => $this->whatsappSuccessRate,
            'emailRate' => $this->emailRate,
            'whatsappRate' => $this->whatsappRate,
            'ultimosLeads' => $this->ultimosLeads,
            'ultimosContatos' => $this->ultimosContatos,
            'cadencias' => $this->cadencias,
            'headersCadencias' => $this->headersCadencias,
            'isAdmin' => $isAdmin,
        ]);
    }
}