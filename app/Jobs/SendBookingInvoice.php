<?php

namespace App\Jobs;

use App\Mail\BookingInvoiceMail;
use App\Models\Booking;
use App\Services\BookingInvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBookingInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $bookingId, public readonly string $email) {}

    public function handle(BookingInvoicePdfService $pdfService): void
    {
        $booking = Booking::query()->with(['turf', 'customer.user'])->findOrFail($this->bookingId);
        $pdf = $pdfService->render($booking);
        Mail::to($this->email)->send(new BookingInvoiceMail('Turf Management', 'INV-'.$booking->booking_number, $pdf));
    }
}
