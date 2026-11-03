<?php

namespace App\Jobs;

use App\Models\ManualSubscriptionPayment;
use App\Services\SubscriptionInvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateSubscriptionInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $paymentId) {}

    public function handle(SubscriptionInvoicePdfService $pdfService): void
    {
        $payment = ManualSubscriptionPayment::query()->findOrFail($this->paymentId);
        $pdfService->generate($payment);
    }
}
