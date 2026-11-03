<?php

namespace App\Services;

use App\Models\ManualSubscriptionPayment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class SubscriptionInvoicePdfService
{
    public function path(ManualSubscriptionPayment $payment): string
    {
        return 'tmp/invoices/invoice-'.($payment->invoice_number ?: 'pending-'.$payment->getKey()).'.pdf';
    }

    public function generate(ManualSubscriptionPayment $payment): string
    {
        $pdf = $this->render($payment);
        Storage::disk('local')->put($this->path($payment), $pdf);

        return $pdf;
    }

    public function render(ManualSubscriptionPayment $payment): string
    {
        $payment->loadMissing(['business.owners', 'planVersion.plan']);

        $business = $payment->business;
        $owner = $business?->owners->first();
        $total = (int) ($payment->final_amount_paisa ?: $payment->amount_paisa);
        $subtotal = (int) ($payment->original_subtotal_paisa ?: $total);
        $discount = (int) ($payment->discount_amount_paisa ?: 0);
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('invoices.subscription', [
            'invoice' => $payment->invoice_number ?: 'Pending',
            'paymentDate' => $payment->payment_date?->format('Y-m-d') ?: '—',
            'transaction' => $payment->transaction_reference,
            'business' => $business?->name ?: '—',
            'owner' => $owner?->name ?: '—',
            'email' => $owner?->email ?: $business?->email ?: '—',
            'plan' => $payment->planVersion?->plan?->name ?: '—',
            'billing' => ($payment->billing_periods ?: 1).' '.$payment->billing_cycle,
            'subtotal' => number_format($subtotal / 100, 2),
            'discount' => number_format($discount / 100, 2),
            'total' => number_format($total / 100, 2),
            'status' => $payment->status,
        ])->render());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
