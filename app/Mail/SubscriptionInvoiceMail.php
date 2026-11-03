<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SubscriptionInvoiceMail extends Mailable
{
    public function __construct(
        public readonly string $businessName,
        public readonly string $invoiceNumber,
        public readonly string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invoice '.$this->invoiceNumber.' - '.$this->businessName);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.subscription-invoice');
    }

    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => $this->pdf,
                'invoice-'.$this->invoiceNumber.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
