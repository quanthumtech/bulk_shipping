<?php

namespace App\Livewire;

use App\Livewire\Forms\CadenciaForm;
use App\Livewire\Plans\PlansModalComponents;
use App\Models\Evolution;
use App\Services\ZohoCrmService;
use Illuminate\Support\Facades\Auth;
use App\Traits\PlanValidator;
use Livewire\Component;
use Mary\Traits\Toast;

class CadenciaCreateEdit extends Component
{
    use Toast;
    use PlanValidator;

    public CadenciaForm $form;

    public $title = 'Nova Cadência';

    public bool $editMode = false;

    public $options = [];

    protected $zohoService;

    public function mount(ZohoCrmService $zohoService, $id = null)
    {
        $this->zohoService = $zohoService;

        if (Auth::user()->chatwoot_accoumts == 5) {
            $this->loadStages();
        } else {
            logger()->info('O usuário não possui a conta do Zoho CRM.');
        }

        if ($id) {
            $cadencia = \App\Models\Cadencias::find($id);
            if ($cadencia) {
                $this->form->setCadencias($cadencia);
                $this->editMode = true;
                $this->title = 'Editar Cadência';
            } else {
                $this->error('Cadência não encontrada.', position: 'toast-top');
                return redirect()->route('cadencias.index');
            }
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
            report($e);
            
            $this->error('Erro ao carregar os estágios do Zoho CRM: ' . $e->getMessage(), position: 'toast-top');
            logger()->error('Erro ao carregar os estágios do Zoho CRM: ' . $e->getMessage());
        }
    }

    public function toggleDay($day)
    {
        $this->form->toggleDay($day);
    }

    public function save()
    {
        if (!$this->validatePlanForAction('create_cadence')) {
            $this->dispatch('open-plans-modal')->to(PlansModalComponents::class);
            return;
        }

        try {
            if ($this->editMode) {
                $this->form->update();
                $this->success('Cadência atualizada com sucesso!', position: 'toast-top', redirectTo: route('cadencias.index'));
            } else {
                $this->form->store();
                $this->success('Cadência cadastrada com sucesso!', position: 'toast-top', redirectTo: route('cadencias.index'));
            }
        } catch (\Exception $e) {
            report($e);
            
            logger()->error('Erro ao salvar a cadência: ' . $e->getMessage());
            $this->error('Erro ao salvar a cadência: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function render()
    {
        $caixasEvolution = collect([['id' => '', 'name' => 'Selecione uma Caixa...']])
            ->concat(
                Evolution::where('user_id', Auth::user()->id)
                    ->where('active', 1)
                    ->get()
                    ->map(function ($evolution) {
                        $url = $evolution->api_post ?? '';
                        $parts = explode('sendText/', $url);
                        $namePart = count($parts) > 1 ? $parts[1] : $url;
                        return [
                            'id' => $evolution->id,
                            'name' => $namePart,
                        ];
                    })
            );

        $datepickerConfig = [
            'mode' => 'multiple',
            'showMonths' => 3, 
            'locale' => 'pt',
            'dateFormat' => 'Y-m-d',
            'inline' => true,
            'static' => true,
        ];

        return view('livewire.cadencia-create-edit', [
            'options' => $this->options,
            'caixasEvolution' => $caixasEvolution,
            'datepickerConfig' => $datepickerConfig,
        ]);
    }
}