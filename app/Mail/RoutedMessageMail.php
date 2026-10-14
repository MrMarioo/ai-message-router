<?php

namespace App\Mail;

use App\Enums\Department;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class RoutedMessageMail extends Mailable
{
    public function __construct(
        private readonly Department $department,
        private readonly string $senderEmail,
        private readonly string $subjectLine,
        private readonly string $summary,
        private readonly string $originalMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->department->value)],
            replyTo: [new Address($this->senderEmail)],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.routed-message',
            with: [
                'department' => $this->department->value,
                'senderEmail' => $this->senderEmail,
                'summary' => $this->summary,
                'originalMessage' => $this->originalMessage,
            ],
        );
    }
}
