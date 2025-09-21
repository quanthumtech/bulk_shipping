<?php

namespace App\Livewire;

use App\Livewire\Forms\SendForm;
use App\Models\Cadencias;
use App\Models\Evolution;
use App\Models\GroupSend;
use App\Models\Send;
use App\Models\User;
use App\Models\EmailIntegration;
use App\Services\ChatwootService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    public array $tags = []; // Array para armazenar os emails selecionados

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
        $this->form->emails = []; // Limpa os emails
        $this->tags = []; // Limpa as tags
        $this->title = 'Enviar Mensagem';
    }

    public function updatedTags($value)
    {
        logger()->info('Tags atualizadas:', ['value' => $value, 'type' => gettype($value)]);

        if (!is_array($this->tags)) {
            $this->tags = [];
        }

        if (is_array($value)) {
            $this->tags = array_unique(array_merge($this->tags, $value));
        } elseif (is_string($value)) {
            $newEmails = array_filter(explode(',', $value));
            $this->tags = array_unique(array_merge($this->tags, $newEmails));
        } else {
            $newEmails = (array) $value;
            $this->tags = array_unique(array_merge($this->tags, $newEmails));
        }

        $this->form->emails = $this->tags;

        logger()->info('Tags sincronizadas:', [
            'tags' => $this->tags,
            'form_emails' => $this->form->emails
        ]);
    }

    public function save(ChatwootService $chatwootService)
    {
        try {
            $this->chatwootService = $chatwootService;

            // Garante que as tags estão sincronizadas antes do envio
            $this->updatedTags($this->tags);

            logger()->info('Iniciando envio:', [
                'phone_numbers' => $this->form->phone_number ?? [],
                'emails' => $this->tags,
                'evolution_id' => $this->form->evolution_id,
                'email_integration_id' => $this->form->email_integration_id
            ]);

            // Envio de mensagens WhatsApp (se houver números de telefone)
            if (!empty($this->form->phone_number)) {
                $evolution = Evolution::find($this->form->evolution_id);
                if (!$evolution) {
                    $this->error('Caixa Evolution inválida.', position: 'toast-top');
                    return;
                }

                foreach ($this->form->phone_number as $index => $phoneNumber) {
                    $contact = collect($this->contatos)->firstWhere('id', $phoneNumber);
                    $contactName = $contact['name'] ?? 'Sem Nome';
                    $this->form->contact_name = $contactName;

                    Log::info('Enviando mensagem WhatsApp para: ' . $contactName . ' - ' . $phoneNumber);

                    $this->chatwootService->sendMessage(
                        $phoneNumber,
                        $this->form->menssage_content,
                        $evolution->api_post,
                        $evolution->apikey,
                        $contactName,
                        null,
                    );

                    if ($index < count($this->form->phone_number) - 1) {
                        sleep(2);
                    }
                }
            }

            // Envio de emails (se houver emails selecionados)
            if (!empty($this->tags)) {
                $emailIntegration = EmailIntegration::find($this->form->email_integration_id);
                if (!$emailIntegration) {
                    $this->error('Conta SMTP inválida.', position: 'toast-top');
                    return;
                }

                // Configura o mailer dinamicamente com as credenciais da integração SMTP
                $encryption = $emailIntegration->encryption ?? 'tls'; // Default para TLS

                config([
                    'mail.mailers.dynamic.transport' => 'smtp',
                    'mail.mailers.dynamic.host' => $emailIntegration->host,
                    'mail.mailers.dynamic.port' => (int) $emailIntegration->port,
                    'mail.mailers.dynamic.encryption' => $encryption,
                    'mail.mailers.dynamic.username' => $emailIntegration->username,
                    'mail.mailers.dynamic.password' => $emailIntegration->password,
                    'mail.mailers.dynamic.stream' => [
                        'ssl' => [
                            'allow_self_signed' => true,
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ],
                    ],
                    'mail.from.address' => $emailIntegration->from_email,
                    'mail.from.name' => $emailIntegration->from_name,
                ]);

                logger()->info('Configuração SMTP:', [
                    'host' => $emailIntegration->host,
                    'port' => $emailIntegration->port,
                    'encryption' => $encryption,
                    'username' => $emailIntegration->username,
                    'from_email' => $emailIntegration->from_email,
                ]);

                // Envio de emails para cada email selecionado
                foreach ($this->tags as $index => $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        try {
                            logger()->info('Tentando enviar email para: ' . $email);

                            Mail::mailer('dynamic')->to($email)->send(new class($this->form->menssage_content) extends \Illuminate\Mail\Mailable {
                                public $content;

                                public function __construct($content)
                                {
                                    $this->content = $content;
                                }

                                public function envelope()
                                {
                                    return new \Illuminate\Mail\Mailables\Envelope(
                                        subject: 'Mensagem do ' . config('app.name'),
                                    );
                                }

                                public function content()
                                {
                                    return new \Illuminate\Mail\Mailables\Content(
                                        view: 'emails.message',
                                        with: ['messageContent' => $this->content],
                                    );
                                }

                                public function attachments()
                                {
                                    return [];
                                }
                            });

                            Log::info('Email enviado com sucesso para: ' . $email);

                            if ($index < count($this->tags) - 1) {
                                sleep(1); // Pequeno delay entre emails
                            }
                        } catch (\Exception $e) {
                            Log::error('Erro ao enviar email para ' . $email . ': ' . $e->getMessage());
                            $this->warning('Falha ao enviar email para ' . $email . '. Verifique os logs.', position: 'toast-top');
                        }
                    } else {
                        Log::warning('Email inválido ignorado: ' . $email);
                    }
                }
            } else {
                Log::info('Nenhum email selecionado para envio');
            }

            // Salva os emails no form antes de persistir
            $this->form->emails = $this->tags;

            $this->form->store();
            $this->success('Envio cadastrado com sucesso!', position: 'toast-top');
            $this->sendModal = false;
        } catch (\Exception $e) {
            logger()->error('Erro ao salvar as mensagens: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'tags' => $this->tags,
                'form_emails' => $this->form->emails,
                'phone_numbers' => $this->form->phone_number ?? [],
            ]);
            $this->error('Erro ao processar o envio: ' . $e->getMessage(), position: 'toast-top');
        }
    }

    public function delete($id)
    {
        Send::find($id)->delete();
        $this->info('Mensagem excluída com sucesso.', position: 'toast-top');
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

        $selectedContacts = collect($this->form->phone_number)->map(function ($contactId) {
            return collect($this->contatos)->firstWhere('id', $contactId);
        })->filter()->toArray();

        $this->contatos = array_merge($selectedContacts, $result);
    }

    public function render()
    {
        $userId = Auth::id();

        $group = GroupSend::find($this->groupId);

        if ($group && $group->phone_number) {
            $groupPhoneNumbers = is_string($group->phone_number)
                ? array_map(function ($phone) {
                    return trim(str_replace(['[', ']', '"'], '', $phone));
                }, explode(',', $group->phone_number))
                : [$group->phone_number];

            $normalizedGroupPhoneNumbers = collect($groupPhoneNumbers)->map(function ($phone) {
                return [
                    'id' => $phone,
                    'name' => $phone,
                ];
            });

            $filteredContacts = collect($this->contatos)->filter(function ($contact) use ($normalizedGroupPhoneNumbers) {
                return $normalizedGroupPhoneNumbers->contains('id', $contact['id']);
            })->map(function ($contact) {
                return [
                    'id' => $contact['id'],
                    'name' => $contact['name'] ?? $contact['id'],
                ];
            })->values();
        } else {
            $filteredContacts = collect();
        }

        $group_table = Send::where('group_id', $this->groupId)
            ->where('user_id', Auth::id())
            ->where(function ($query) {
                $query->where('phone_number', 'like', '%' . $this->search . '%')
                    ->orWhere('contact_name', 'like', '%' . $this->search . '%')
                    ->orWhere('message_content', 'like', '%' . $this->search . '%');
            })
            ->with('user')
            ->paginate($this->perPage);

        foreach ($group_table as $group) {
            $group->menssage = Str::limit($group->message_content, 50);
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

        $cadencias = collect([['id' => '', 'name' => 'Selecione uma cadência']])
            ->concat(Cadencias::where('user_id', $userId)->get());

        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'bg-green-500/20 w-1 text-black'],
            ['key' => 'contact_name', 'label' => 'Nome do Contato'],
            ['key' => 'formatted_phone_number', 'label' => 'Tel de Contato'],
            ['key' => 'criado_por', 'label' => 'Remetente'],
            ['key' => 'menssage', 'label' => 'Mensagem'],
            ['key' => 'formatted_created_at', 'label' => 'Enviado']
        ];

        $descriptionCard = 'Utilize este recurso para enviar mensagens em massa para os contatos selecionados.
                    Crie cadências personalizadas, programe intervalos entre os envios e alcance seus
                    contatos de forma eficiente e organizada. Obs: Não esqueça de clicar no "X" para
                    zerar o campo, assim você pode selecionar novos contatos!';

        $configDatePicker = ['locale' => 'pt'];

        $caixasEvolution = collect([['id' => '', 'name' => 'Selecione uma Caixa...']])
            ->concat(
                Evolution::where('user_id', $userId)
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

        $contasSmtp = collect([['id' => '', 'name' => 'Selecione uma Conta SMTP...']])
            ->concat(
                EmailIntegration::where('user_id', $userId)
                    ->where('active', true)
                    ->get()
                    ->map(function ($emailIntegration) {
                        return [
                            'id' => $emailIntegration->id,
                            'name' => $emailIntegration->from_email . ' (' . $emailIntegration->host . ')',
                        ];
                    })
            );

        return view('livewire.send-index', [
            'headers' => $headers,
            'group_table' => $group_table,
            'descriptionCard' => $descriptionCard,
            'configDatePicker' => $configDatePicker,
            'cadencias' => $cadencias,
            'filteredContacts' => $filteredContacts->toArray(),
            'caixasEvolution' => $caixasEvolution,
            'contasSmtp' => $contasSmtp,
            'tags' => $this->tags, // Passa as tags para a view para debug
        ]);
    }
}
