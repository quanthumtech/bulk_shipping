<?php
namespace App\Livewire;

use App\Models\Etapa;
use App\Models\Cadencias;
use App\Models\Etapas;
use Livewire\Component;
use Livewire\WithPagination;

class EtapasIndex extends Component
{
    use WithPagination;

    public $cadenciaId;
    public $titulo = '';
    public $tempo = '';
    public $unidade_tempo = 'dias';
    public bool $etapaModal = false;

    protected $rules = [
        'titulo' => 'required|string|max:255',
        'tempo' => 'required|integer|min:1|max:30',
        'unidade_tempo' => 'required|in:dias,horas,minutos',
    ];

    public function mount($cadenciaId)
    {
        $this->cadenciaId = $cadenciaId;
    }

    public function showModal()
    {
        $this->reset(['titulo', 'tempo', 'unidade_tempo']);
        $this->etapaModal = true;
    }

    public function save()
    {
        $this->validate();

        Etapas::create([
            'cadencia_id' => $this->cadenciaId,
            'titulo' => $this->titulo,
            'tempo' => $this->tempo,
            'unidade_tempo' => $this->unidade_tempo,
        ]);

        session()->flash('success', 'Etapa adicionada com sucesso!');
        $this->etapaModal = false;
    }

    public function delete($id)
    {
        Etapas::findOrFail($id)->delete();
        session()->flash('success', 'Etapa removida com sucesso!');
    }

    public function render()
    {
        $cadencia = Cadencias::findOrFail($this->cadenciaId);
        $etapas = Etapas::where('cadencia_id', $this->cadenciaId)->paginate(5);

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'titulo', 'label' => 'Título'],
            ['key' => 'tempo', 'label' => 'Tempo'],
            ['key' => 'unidade_tempo', 'label' => 'Unidade de Tempo'],
            ['key' => 'actions', 'label' => 'Ações'],
        ];

        $options = [
            ['value' => '', 'name' => 'Selecione...'],
            ['id' => 'dias', 'name' => 'Dias'],
            ['id' => 'horas', 'name' => 'Horas'],
            ['id' => 'minutos', 'name' => 'Minutos'],
        ];

        return view('livewire.etapas-index', [
            'cadencia' => $cadencia,
            'etapas' => $etapas,
            'headers' => $headers,
            'options' => $options,
        ]);
    }
}
