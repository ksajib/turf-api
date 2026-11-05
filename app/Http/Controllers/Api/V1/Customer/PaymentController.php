<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\SslCommerzService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function success(Request $request, SslCommerzService $ssl)
    {
        return $this->complete($request, $ssl, true);
    }

    public function ipn(Request $request, SslCommerzService $ssl)
    {
        return $this->complete($request, $ssl, false);
    }

    public function fail(Request $request)
    {
        return $this->redirect($request, 'failed');
    }

    public function cancel(Request $request)
    {
        return $this->redirect($request, 'cancelled');
    }

    private function complete(Request $request, SslCommerzService $ssl, bool $redirect): mixed
    {
        $transaction = (string) ($request->input('tran_id') ?? '');
        $validationId = (string) ($request->input('val_id') ?? '');
        $booking = Booking::query()->where('booking_number', $transaction)->first();
        if (! $booking || ! $validationId) return $redirect ? $this->redirect($request, 'failed') : response()->json(['message' => 'Payment could not be matched.'], 422);

        try { $validated = $ssl->validate($validationId); } catch (\Throwable) { return $redirect ? $this->redirect($request, 'failed') : response()->json(['message' => 'Payment validation failed.'], 422); }
        $valid = in_array(($validated['status'] ?? ''), ['VALID', 'VALIDATED'], true) && (float) ($validated['amount'] ?? 0) === (float) $booking->price_taka && ($validated['currency'] ?? 'BDT') === 'BDT';
        if ($valid) $booking->update(['payment_status' => 'paid', 'amount_received_taka' => $booking->price_taka, 'payment_method' => 'sslcommerz', 'transaction_reference' => $validationId, 'status' => $booking->status === 'pending' ? 'confirmed' : $booking->status, 'confirmed_at' => $booking->confirmed_at ?? now()]);
        return $redirect ? $this->redirect($request, $valid ? 'success' : 'failed') : response()->json(['message' => $valid ? 'Payment validated.' : 'Payment validation failed.'], $valid ? 200 : 422);
    }

    private function redirect(Request $request, string $status)
    {
        return redirect()->away(config('services.sslcommerz.frontend_url').'/customer/turfs?payment='.$status);
    }
}
