<?php

namespace App\Livewire\Forms;

use App\Models\Cadencias;
use App\Models\SyncFlowLeads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Form;
use Mary\Traits\Toast;
use Mary\Traits\WithMediaSync;

class CadenciaForm extends Form
{
    use WithFileUploads, WithMediaSync, Toast;

    public ?Cadencias $cadencias = null;

    public $name;

    public $hora_inicio = '08:00:00';

    public $hora_fim = '23:59:00';

    public $description;

    public $active = true;

    public $stage = '';

    public $evolution_id;

    public $days_of_week = [1, 2, 3, 4, 5];

    public $excluded_dates = [];

    public $excluded_dates_string = '';

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'active' => 'required|boolean',
            'hora_inicio' => 'required',
            'hora_fim' => 'required|after:hora_inicio',
            'stage' => 'nullable|string',
            'evolution_id' => 'required|exists:evolutions,id',
            'days_of_week' => 'required|array|min:1',
            'days_of_week.*' => 'integer|between:1,7',
            'excluded_dates' => 'array',
            'excluded_dates.*' => 'date_format:Y-m-d',
            'excluded_dates_string' => 'nullable|string',
        ];
    }

    public function setCadencias(Cadencias $cadencias)
    {
        $this->cadencias = $cadencias;
        $this->name = $cadencias->name;
        $this->hora_inicio = $cadencias->hora_inicio ?? '08:00:00';
        $this->hora_fim = $cadencias->hora_fim ?? '23:59:00';
        $this->description = $cadencias->description;
        $this->stage = $cadencias->stage;
        $this->active = (bool) $cadencias->active;
        $this->evolution_id = $cadencias->evolution_id;
        $this->days_of_week = $cadencias->days_of_week ?? [1, 2, 3, 4, 5];
        $this->excluded_dates = $cadencias->excluded_dates ?? [];
        $this->excluded_dates_string = implode(', ', $this->excluded_dates);
    }

    public function toggleDay($day)
    {
        $day = (int) $day;
        if (in_array($day, $this->days_of_week)) {
            $this->days_of_week = array_values(array_filter($this->days_of_week, fn($d) => $d !== $day));
        } else {
            $this->days_of_week[] = $day;
            sort($this->days_of_week);
        }
        $this->days_of_week = array_values($this->days_of_week);
    }

    public function store()
    {
        if (is_string($this->excluded_dates)) {
            $this->excluded_dates = array_values(array_filter(array_map('trim', explode(',', $this->excluded_dates))));
        }

        $this->validate();

        $data = [
            'name' => $this->name,
            'hora_inicio' => $this->hora_inicio,
            'hora_fim' => $this->hora_fim,
            'description' => $this->description,
            'stage' => $this->stage ?? 'Sem Estágio',
            'active' => $this->active,
            'user_id' => auth()->id(),
            'evolution_id' => $this->evolution_id,
            'days_of_week' => $this->days_of_week,
            'excluded_dates' => $this->excluded_dates,
        ];

        $cadencias = Cadencias::create($data);

        if (Auth::user()->chatwoot_accounts == 5) {
            $sync_emp = SyncFlowLeads::whereRaw('UPPER(estagio) = ?', [strtoupper($this->stage)])->first();
            if ($sync_emp) {
                $sync_emp->cadencia_id = $cadencias->id;
                $sync_emp->save();
            }
        }

        $this->reset();
    }

    public function update()
    {
        if (is_string($this->excluded_dates)) {
            $this->excluded_dates = array_values(array_filter(array_map('trim', explode(',', $this->excluded_dates))));
        }

        Log::info('Dados a salvar (store)', [
            'name' => $this->name,
            'hora_inicio' => $this->hora_inicio,
            'hora_fim' => $this->hora_fim,
            'description' => $this->description,
            'stage' => $this->stage ?? 'Sem Estágio',
            'active' => $this->active,
            'user_id' => auth()->id(),
            'evolution_id' => $this->evolution_id,
            'days_of_week' => $this->days_of_week,
            'excluded_dates' => $this->excluded_dates
        ]);

        $this->validate();

        $data = [
            'name' => $this->name,
            'hora_inicio' => $this->hora_inicio,
            'hora_fim' => $this->hora_fim,
            'description' => $this->description,
            'stage' => $this->stage,
            'active' => $this->active,
            'evolution_id' => $this->evolution_id,
            'days_of_week' => $this->days_of_week,
            'excluded_dates' => $this->excluded_dates,
        ];

        Log::info('Dados a salvar (update)', $data);

        $this->cadencias->update($data);

        $this->reset();
    }
}