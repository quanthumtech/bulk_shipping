<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlanLimitExceeded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $reason
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Limite Diário de Leads do Seu Plano Foi Excedido',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.plan-limit-exceeded',
            with: [
                'user' => $this->user,
                'reason' => $this->reason,
            ],
        );
    }
}