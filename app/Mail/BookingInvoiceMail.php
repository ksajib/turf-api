<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingInvoiceMail extends Mailable
{
    public function __construct(public readonly string $businessName, public readonly string $invoiceNumber, public readonly string $pdf) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Booking invoice '.$this->invoiceNumber);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.booking-invoice');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, 'invoice-'.$this->invoiceNumber.'.pdf')->withMime('application/pdf')];
    }
}
