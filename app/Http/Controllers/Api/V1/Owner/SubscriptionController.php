<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Jobs\SendSubscriptionInvoice;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Booking;
use App\Models\ManualSubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Turf;
use App\Services\OwnerBusinessScope;
use App\Services\SubscriptionInvoicePdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    public function __construct(private readonly OwnerBusinessScope $scope) {}

    public function show(Request $request)
    {
        $business = $this->business($request);
        $subscription = $business->activeSubscription?->load(['planVersion.plan', 'payment']);
        $payments = ManualSubscriptionPayment::query()->where('business_id', $business->id)->with('planVersion.plan')->latest('payment_date')->get();
        $monthStart = now()->startOfMonth();
        $plan = $subscription?->planVersion;
        $usage = ['turfs' => ['used' => Turf::where('business_id', $business->id)->count(), 'limit' => $plan?->max_turfs], 'staff' => ['used' => $business->owners()->where('users.role', '!=', 'business-owner')->count(), 'limit' => $plan?->max_staff], 'bookings' => ['used' => Booking::where('business_id', $business->id)->where('created_at', '>=', $monthStart)->count(), 'limit' => null]];

        return response()->json(['data' => ['subscription' => $subscription ? $this->subscription($subscription) : null, 'usage' => $usage, 'plans' => $this->plans(), 'invoices' => $payments->map(fn (ManualSubscriptionPayment $payment) => $this->invoice($payment))->values()]]);
    }

    public function downloadInvoice(Request $request, ManualSubscriptionPayment $payment, SubscriptionInvoicePdfService $pdfService)
    {
        $this->assertPayment($request, $payment);
        $pdf = $pdfService->render($payment);
        return response()->streamDownload(fn () => print $pdf, 'invoice-'.($payment->invoice_number ?: $payment->id).'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function pause(Request $request)
    {
        $business = $this->business($request);
        $subscription = $business->activeSubscription;
        abort_unless($subscription, 422, 'There is no active subscription to pause.');
        if (! $subscription->pause_at_period_end) $subscription->update(['pause_at_period_end' => true]);
        return response()->json(['data' => $this->subscription($subscription->refresh()->load(['planVersion.plan', 'payment'])), 'message' => 'Plan pause is scheduled for the end of the current billing period.']);
    }

    public function sendInvoice(Request $request, ManualSubscriptionPayment $payment)
    {
        $this->assertPayment($request, $payment);
        $payment->load('business.owners');
        $email = $payment->business?->owners->first()?->email ?: $payment->business?->email;
        abort_unless($email, 422, 'No billing email is available for this business.');
        SendSubscriptionInvoice::dispatch($payment->id, $email)->afterCommit();
        return response()->json(['message' => 'Subscription invoice email queued successfully.'], Response::HTTP_ACCEPTED);
    }

    private function business(Request $request): Business
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'business-owner', 403, 'Only the business owner can view subscription billing.');
        $id = $this->scope->primary($user);
        return Business::query()->whereKey($id)->with('activeSubscription.planVersion.plan')->firstOrFail();
    }

    private function assertPayment(Request $request, ManualSubscriptionPayment $payment): void
    {
        $business = $this->business($request);
        abort_unless((string) $payment->business_id === (string) $business->id, 404, 'Invoice not found.');
    }

    private function plans(): array
    {
        return SubscriptionPlan::query()->where('status', SubscriptionPlan::STATUS_PUBLISHED)->with('versions')->orderBy('name')->get()->map(function (SubscriptionPlan $plan): ?array {
            $version = $plan->versions->firstWhere('version', $plan->current_version);
            if (! $version) return null;
            return ['id' => $plan->id, 'name' => $plan->name, 'description' => $plan->description, 'monthly_price_taka' => (int) round($version->monthly_price_paisa / 100), 'yearly_price_taka' => (int) round($version->yearly_price_paisa / 100), 'features' => array_values(array_filter(['Up to '.($version->max_turfs ?? 'unlimited').' turfs', 'Up to '.($version->max_staff ?? 'unlimited').' staff accounts', $version->description])), 'current_version_id' => $version->id];
        })->filter()->values()->all();
    }

    private function subscription(BusinessSubscription $subscription): array
    {
        $version = $subscription->planVersion;
        return ['id' => $subscription->id, 'plan' => $version?->plan?->name, 'billing_cycle' => $subscription->billing_cycle, 'price_taka' => (int) round($subscription->price_paisa / 100), 'status' => $subscription->status, 'pause_at_period_end' => (bool) $subscription->pause_at_period_end, 'starts_at' => $subscription->starts_at?->toISOString(), 'expires_at' => $subscription->expires_at?->toISOString(), 'payment_method' => $subscription->payment?->payment_method, 'transaction_reference' => $subscription->payment?->transaction_reference, 'limits' => ['turfs' => $version?->max_turfs, 'staff' => $version?->max_staff, 'branches' => $version?->max_branches]];
    }

    private function invoice(ManualSubscriptionPayment $payment): array
    {
        return ['id' => $payment->id, 'invoice_number' => $payment->invoice_number, 'date' => $payment->payment_date?->toISOString(), 'amount_taka' => (int) round(($payment->final_amount_paisa ?: $payment->amount_paisa) / 100), 'status' => $payment->status, 'payment_method' => $payment->payment_method, 'plan' => $payment->planVersion?->plan?->name];
    }
}
