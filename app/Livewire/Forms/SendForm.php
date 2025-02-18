<?php

namespace App\Livewire\Forms;

use App\Models\Send;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Livewire\WithFileUploads;
use Mary\Traits\WithMediaSync;

class SendForm extends Form
{
    use WithFileUploads, WithMediaSync;

    public ?Send $sends = null;

    protected $rules = [
        'phone_number'     => 'required|array',
        'menssage_content' => 'required|string',
        'message_interval' => 'nullable|string',
        'sent_at'          => 'nullable|date',
        'active'           => 'nullable|boolean',
        'status'           => 'nullable|string',
        'start_date'       => 'nullable|date',
        'end_date'         => 'nullable|date|after_or_equal:start_date',
        'interval'         => 'nullable|integer|min:1',
        'file'             => 'nullable|array',
        'file.*'           => 'mimes:jpg,jpeg,png,pdf,docx|max:2048',
    ];

    public $contato, $file, $phone_number, $sent_at, $active,
            $status, $contact_name, $menssage_content, $message_interval,
            $group_id, $user_id, $start_date, $end_date, $interval;

    public function setSend(Send $sends)
    {
        $this->sends               = $sends;
        $this->contact_name        = $sends->contact_name;
        $this->phone_number        = $sends->phone_number ? json_decode($sends->phone_number, true) : [];
        $this->menssage_content    = $sends->menssage_content;
        $this->message_interval    = $sends->message_interval;
        $this->sent_at             = $sends->sent_at;
        $this->active              = (bool) $sends->active;
        $this->status              = $sends->status;
        $this->group_id            = $sends->group_id;
        $this->user_id             = $sends->user_id;
        $this->start_date          = $sends->start_date;
        $this->end_date            = $sends->end_date;
        $this->interval            = $sends->interval;
        $this->file                = $sends->file ? asset('send/' . $sends->file) : null;

    }

    public function store()
    {
        $this->validate();

        $data = [
            'contact_name'    => $this->contact_name,
            'phone_number'    => json_encode($this->phone_number),
            'message_content' => $this->menssage_content,
            'message_interval'=> $this->message_interval,
            'sent_at'         => $this->sent_at,
            'active'          => $this->active,
            'status'          => $this->status,
            'user_id'         => auth()->id(),
            'group_id'        => $this->sends->group_id ?? $this->group_id,
            'start_date'      => $this->start_date,
            'end_date'        => $this->end_date,
            'interval'        => $this->interval,
        ];

        if ($this->file && is_array($this->file)) {
            $uploadedFiles = [];
            foreach ($this->file as $file) {
                $uploadedFiles[] = $file->store('send', 'public');
            }
            $data['file'] = json_encode($uploadedFiles);
        } elseif ($this->file) {
            $data['file'] = $this->file->store('send', 'public');
        }

        Send::create($data);

        $this->reset();
    }

    public function aiSuggestion($context = "")
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('DEEPSEEK_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api.deepseek.com/v1/chat/completions', [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Sugira uma mensagem profissional para: $context. Mantenha em 160 caracteres."
                    ]
                ]
            ]);

            if ($response->successful()) {
                $this->menssage_content = $response->json()['choices'][0]['message']['content'];
            }

        } catch (\Exception $e) {
            throw new \Exception("Erro na API: " . $e->getMessage());
        }
    }
}
