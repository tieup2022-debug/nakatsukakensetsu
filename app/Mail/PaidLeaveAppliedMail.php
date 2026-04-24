<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaidLeaveAppliedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $applicantName,
        public string $rangeText,
        public ?string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '有給休暇の申請があります',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.paid-leave-applied',
        );
    }
}
