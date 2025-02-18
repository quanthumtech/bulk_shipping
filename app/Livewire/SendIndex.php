<?php

namespace App\Livewire;

use App\Livewire\Forms\SendForm;
use App\Models\GroupSend;
use App\Models\Send;
use App\Models\User;
use App\Services\ChatwootService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SendIndex extends Component
{
    use WithFileUploads, Toast;

    public SendForm $form;

    public $title = '';

    public bool $sendModal = false;

    public bool $editMode = false;

    protected $chatwootService;

    public $contatos;

    public $groupId;

    public $search = '';

    public int $perPage = 3;

    public function mount(ChatwootService $chatwootService, $groupId)
    {
        $this->chatwootService = $chatwootService;

        $this->contatos = $this->chatwootService->getContatos();

        $this->groupId = $groupId;
        $this->form->group_id = $groupId;
    }

    public function showModal()
    {
        $this->form->reset();
        $this->editMode = false;
        $this->sendModal = true;
        $this->form->group_id = $this->groupId;
        $this->title = 'Enviar Mensagem';
    }

    public function save(ChatwootService $chatwootService)
    {
        try {

            $this->chatwootService = $chatwootService;

            // Collect contact names and send messages
            foreach ($this->form->phone_number as $index => $phoneNumber) {
                $contact = collect($this->contatos)->firstWhere('id', $phoneNumber);
                $contactName = $contact['name'] ?? 'Sem Nome';
                $this->form->contact_name = $contactName;

                $this->chatwootService->sendMessage($phoneNumber, $this->form->menssage_content);

                if ($index < count($this->form->phone_number) - 1) {
                    sleep(2);
                }
            }

            $this->form->store();
            $this->success('Envio cadastrado com sucesso!', position: 'toast-top');
            $this->sendModal = false;

        } catch (\Exception $e) {
            $this->error('Erro ao salvar as Menssagens.', position: 'toast-top');

        }
    }

    public function delete($id)
    {
        Send::find($id)->delete();
        $this->info('Menssagem excluída com sucesso.', position: 'toast-top');
    }

    public function updatedUserSearchableId($value)
    {
        $existing = collect($this->form->phone_number);
        $selected = collect($this->contatos)->firstWhere('id', $value);

        if ($selected && !$existing->contains($value)) {
            $this->form->phone_number[] = $value;
        }
    }

    public function searchContatosf($value = '')
    {
        $this->chatwootService = app(ChatwootService::class);
        $result = $this->chatwootService->searchContatosApi($value);

        // Mantém os contatos já selecionados no campo `phone_number`
        $selectedContacts = collect($this->form->phone_number)->map(function ($contactId) {
            return collect($this->contatos)->firstWhere('id', $contactId);
        })->filter()->toArray();

        // Garante que os contatos selecionados não sejam removidos
        $this->contatos = array_merge($selectedContacts, $result);
    }

    public function render()
    {
        $userId = auth()->id();

        $group = GroupSend::find($this->groupId);

        if ($group && $group->phone_number) {
            $groupPhoneNumbers = is_string($group->phone_number)
                ? array_map(function ($phone) {
                    return trim(str_replace(['[', ']', '"'], '', $phone));
                }, explode(',', $group->phone_number))
                : [$group->phone_number];

            // Normalizar os números para o formato desejado
            $normalizedGroupPhoneNumbers = collect($groupPhoneNumbers)->map(function ($phone) {
                return [
                    'id' => $phone,
                    'name' => $phone, // Ou aplique uma lógica para definir o nome se necessário
                ];
            });

            // Filtrar contatos com base nos números normalizados
            $filteredContacts = collect($this->contatos)->filter(function ($contact) use ($normalizedGroupPhoneNumbers) {
                return $normalizedGroupPhoneNumbers->contains('id', $contact['id']);
            })->map(function ($contact) {
                // Garantir que todos os contatos tenham um formato consistente
                return [
                    'id' => $contact['id'],
                    'name' => $contact['name'] ?? $contact['id'], // 'name' ou fallback para 'id'
                ];
            })->values(); // Reseta as chaves para consistência
        } else {
            $filteredContacts = collect();
        }

        $group_table = Send::where('group_id', $this->groupId)
                        ->where(function ($query) {
                            $query->where('user_id', auth()->id());
                        })
                        ->where(function ($query) {
                            $query->where('phone_number', 'like', '%' . $this->search . '%')
                                ->orWhere('contact_name', 'like', '%' . $this->search . '%')
                                ->orWhere('message_content', 'like', '%' . $this->search . '%');
                        })
                        ->with('user')
                        ->paginate($this->perPage);

        foreach($group_table as $group){
            $group->menssage =  Str::limit($group->message_content, 50);
            $group->formatted_created_at = Carbon::parse($group->created_at)->format('d/m/Y');
            $group->criado_por = User::where('id', $group->user_id)->first()->name ?? 'Não atribuído';

            $phoneNumbers = json_decode($group->phone_number, true);
            if (is_array($phoneNumbers)) {
                $limitedNumbers = array_slice($phoneNumbers, 0, 3);
                $group->formatted_phone_number = implode(', ', $limitedNumbers);

                if (count($phoneNumbers) > 3) {
                    $group->formatted_phone_number .= ', ...';
                }
            } else {
                $group->formatted_phone_number = $group->phone_number;
            }

        }

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'contact_name', 'label' => 'Nome do Contato'],
            ['key' => 'formatted_phone_number', 'label' => 'Tel de Contato'],
            ['key' => 'criado_por', 'label' => 'Remetente'],
            ['key' => 'menssage', 'label' => 'Menssagem'],
            ['key' => 'formatted_created_at', 'label' => 'Enviado']
        ];

        $descriptionCard = 'Utilize este recurso para enviar mensagens em massa para os contatos selecionados.
                            Crie cadências personalizadas, programe intervalos entre os envios e alcance seus
                            contatos de forma eficiente e organizada. Obs: Não esqueça de clicar no "X" para
                            zerar o campo, assim você pode selecionar novos contatos!';

        $configDatePicker = ['locale' => 'pt'];

        return view('livewire.send-index', [
            'headers'          => $headers,
            'group_table'      => $group_table,
            'descriptionCard'  => $descriptionCard,
            'configDatePicker' => $configDatePicker,
            'filteredContacts' => $filteredContacts->toArray()
        ]);
    }
}
