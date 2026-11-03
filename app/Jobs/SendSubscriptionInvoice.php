<?php

namespace App\Jobs;

use App\Mail\SubscriptionInvoiceMail;
use App\Models\ManualSubscriptionPayment;
use App\Services\SubscriptionInvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $paymentId, public readonly string $email) {}

    public function handle(SubscriptionInvoicePdfService $pdfService): void
    {
        $payment = ManualSubscriptionPayment::query()->with(['business.owners'])->findOrFail($this->paymentId);
        $pdf = $pdfService->render($payment);
        $businessName = $payment->business?->name ?: 'Turf Management';
        Mail::to($this->email)->send(new SubscriptionInvoiceMail($businessName, $payment->invoice_number ?: $payment->getKey(), $pdf));
    }
}
