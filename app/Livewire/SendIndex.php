<?php

namespace App\Livewire;

use App\Livewire\Forms\SendForm;
use App\Models\Send;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mary\Traits\Toast;
use Livewire\WithFileUploads;

class SendIndex extends Component
{
    use WithFileUploads, Toast;

    public SendForm $form;

    public $title = '';

    public bool $sendModal = false;

    public bool $editMode = false;

    public function showModal()
    {
        $this->form->reset();
        $this->editMode = false;
        $this->sendModal = true;
        $this->title = 'Enviar Mensagem';
    }

    public function save()
    {
        try {
            if ($this->editMode) {
                $this->form->update();
                $this->editMode = false;
                $this->success('Envio atualizado com sucesso!', position: 'toast-top');
            } else {
                $this->form->store();
                $this->success('Envio cadastrado com sucesso!', position: 'toast-top');
            }

            $this->sendModal = false;

        } catch (\Exception $e) {
            dd($e);
            $this->error('Erro ao salvar as Menssagens.', position: 'toast-top');

        }
    }

    public function edit($id)
    {
        $group = Send::find($id);

        if ($group) {
            $this->form->setSend($group);
            $this->editMode = true;
            $this->sendModal = true;
            $this->title = 'Editar Grupo';
        } else {
            $this->info('Grupo não encontrado.', position: 'toast-top');
        }
    }

    public function delete($id)
    {
        Send::find($id)->delete();
        $this->info('Envio excluído com sucesso.', position: 'toast-top');
    }

    public function render()
    {
        $userId = auth()->id();

        $descriptionCard = 'Comece criando um envio em massa ou por cadência.';

        $configDatePicker = ['locale' => 'pt'];

        // Recupera os contatos da API
        $contatos = $this->getContatosFromApi();

        return view('livewire.send-index', [
            'descriptionCard'  => $descriptionCard,
            'configDatePicker' => $configDatePicker,
            'contatos'         => $contatos
        ]);
    }

    /**
     * Recupera contatos da API Chatwoot.
     */
    private function getContatosFromApi()
    {
        $url = 'https://chatwoot.plataformamundo.com.br/api/v1/accounts/8/contacts';
        $token = 'VpWgywCNzCoWPvqtmetZ1Wxw';

        try {
            $response = Http::withHeaders([
                'api_access_token' => $token,
            ])->get($url, [
                'sort' => '-email',
                'page' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Verifica se a chave 'payload' existe
                if (isset($data['payload'])) {
                    return collect($data['payload'])->map(function ($contact) {
                        return [
                            'id' => $contact['phone_number'], // ID do contato, o phone do contato
                            'name' => $contact['name'] ?? $contact['phone_number'], // Nome ou telefone
                        ];
                    })->toArray();
                }
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Erro ao recuperar contatos da API: ' . $e->getMessage());
            return [];
        }
    }

}
