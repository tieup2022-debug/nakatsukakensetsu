<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SystemInquiryReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $inquiryId,
        public string $submittedBy,
        public string $submittedAt,
        public string $inquiryBody,
        public string $inquiryListUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【Nakatsuka DX】新しいお問い合わせが届きました',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.system-inquiry-received',
        );
    }
}
