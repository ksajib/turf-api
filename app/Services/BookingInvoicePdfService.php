<?php

namespace App\Services;

use App\Models\Booking;
use Dompdf\Dompdf;
use Dompdf\Options;

class BookingInvoicePdfService
{
    public function render(Booking $booking): string
    {
        $booking->loadMissing(['turf', 'customer.user']);
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('invoices.booking', ['booking' => $booking, 'invoice' => 'INV-'.$booking->booking_number])->render());
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        return $pdf->output();
    }
}
