<?php

namespace App\Livewire;

use App\Models\Cadencias;
use App\Services\ZohoCrmService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class CadenciaIndex extends Component
{
    use WithPagination, Toast;

    public $search = '';

    public int $perPage = 3;

    public $options = [];

    protected $zohoService;

    public function mount(ZohoCrmService $zohoService)
    {
        $this->zohoService = $zohoService;

        if (Auth::user()->chatwoot_accounts == 5) {
            $this->loadStages();
        } else {
            Log::info('O usuário não possui a conta do Zoho CRM.');
        }
    }

    public function loadStages()
    {
        try {
            $stages = $this->zohoService->getStages();
            $this->options = array_map(function ($stage) {
                return [
                    'id' => $stage['display_value'],
                    'name' => $stage['display_value'],
                ];
            }, $stages);
        } catch (\Exception $e) {
            $this->error('Erro ao carregar os estágios do Zoho CRM: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function delete($id)
    {
        Cadencias::find($id)->delete();
        $this->success('Cadência excluída com sucesso!', position: 'toast-top');
    }

    public function render()
    {
        $user = Auth::user();
        $cadencias_table = Cadencias::where('user_id', $user->id)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->paginate($this->perPage);

        foreach ($cadencias_table as $cadencia) {
            $cadencia->active = $this->getActiveUser($cadencia->active);
            $cadencia->updatet_date = Carbon::parse($cadencia->updated_at)->format('d/m/Y H:i');
        }

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'description', 'label' => 'Descrição'],
            ['key' => 'updatet_date', 'label' => 'Última Alteração'],
            ['key' => 'active', 'label' => 'Ativo'],
        ];

        $descriptionCard = 'Cadências são fluxos de comunicação que podem ser aplicados a um ou mais contatos. Cada cadência é composta por uma série de etapas, que podem ser mensagens de texto, e-mails, ligações, entre outros. Clique no botão "+" para criar as etapas da sua cadência.';

        return view('livewire.cadencia-index', [
            'cadencias_table' => $cadencias_table,
            'headers' => $headers,
            'descriptionCard' => $descriptionCard,
        ]);
    }

    public function getActiveUser($active)
    {
        $type = [
            1 => 'Ativo',
            0 => 'Inativo'
        ];

        return $type[$active] ?? '';
    }
}