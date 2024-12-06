<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\Send;
use App\Services\ChatwootService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $service;

    public function __construct()
    {
        $this->service = new ChatwootService();
    }

    public function handle()
    {
        $messages = Send::where('active', 1)
            ->whereNull('sent_at')
            ->where('start_date', '<=', now())
            ->get();

        foreach ($messages as $message) {
            $nextSendDate = $message->start_date->addDays($message->interval);

            if ($nextSendDate <= now()) {
                $contacts = json_decode($message->phone_number, true);

                if (is_array($contacts)) {
                    foreach ($contacts as $phoneNumber) {
                        $this->service->sendMessage($phoneNumber, $message->message_interval);
                    }
                }

                $message->update([
                    'sent_at' => now(),
                    'status' => 'Enviado',
                    'start_date' => $nextSendDate,
                ]);
            }
        }
    }
}
