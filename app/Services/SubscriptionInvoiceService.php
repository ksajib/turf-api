<?php

namespace App\Services;

use App\Models\ManualSubscriptionPayment;
use Illuminate\Support\Str;

class SubscriptionInvoiceService
{
    public function issue(ManualSubscriptionPayment $payment): ManualSubscriptionPayment
    {
        if ($payment->invoice_number) return $payment;

        do {
            $invoiceNumber = 'INV-'.now('Asia/Dhaka')->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (ManualSubscriptionPayment::query()->where('invoice_number', $invoiceNumber)->exists());

        $payment->forceFill([
            'invoice_number' => $invoiceNumber,
            'invoice_status' => $this->statusFor($payment->status),
            'invoice_issued_at' => now(),
        ])->save();

        return $payment;
    }

    public function syncStatus(ManualSubscriptionPayment $payment): ManualSubscriptionPayment
    {
        $payment->forceFill(['invoice_status' => $this->statusFor($payment->status)])->save();
        return $payment;
    }

    private function statusFor(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            ManualSubscriptionPayment::STATUS_VERIFIED => 'paid',
            ManualSubscriptionPayment::STATUS_REJECTED => 'rejected',
            default => 'pending',
        };
    }
}
