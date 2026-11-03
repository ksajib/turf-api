<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\ManualSubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;

class PlatformDashboardController extends Controller
{
    public function overview()
    {
        $now = CarbonImmutable::now('UTC');
        $monthStart = $now->startOfMonth();
        $sixMonthsAgo = $monthStart->subMonths(5);

        $verifiedPayments = ManualSubscriptionPayment::query()->where('status', ManualSubscriptionPayment::STATUS_VERIFIED);
        $pendingPayments = ManualSubscriptionPayment::query()->where('status', ManualSubscriptionPayment::STATUS_PENDING);
        $activeSubscriptions = BusinessSubscription::query()->where('status', BusinessSubscription::STATUS_ACTIVE)->where('starts_at', '<=', $now)->where('expires_at', '>', $now);

        $plans = $activeSubscriptions->with('planVersion.plan')->get()->groupBy(fn (BusinessSubscription $subscription) => $subscription->planVersion?->plan?->name ?: 'Unassigned')->map(fn ($items, $name) => ['name' => $name, 'count' => $items->count()])->values();
        $revenueTrend = collect(range(0, 5))->map(function (int $offset) use ($monthStart): array {
            $start = $monthStart->subMonths(5 - $offset);
            $end = $start->addMonth();
            $amount = (int) ManualSubscriptionPayment::query()->where('status', ManualSubscriptionPayment::STATUS_VERIFIED)->where('payment_date', '>=', $start)->where('payment_date', '<', $end)->sum('final_amount_paisa');
            return ['month' => $start->format('M'), 'revenue_paisa' => $amount];
        });

        $businesses = Business::query()->with(['owners', 'activeSubscription.planVersion.plan'])->latest()->limit(6)->get()->map(function (Business $business): array {
            $subscription = $business->activeSubscription;
            return [
                'id' => $business->id,
                'name' => $business->name,
                'owner' => $business->owners->first()?->name,
                'plan' => $subscription?->planVersion?->plan?->name,
                'status' => $business->status,
                'revenue_paisa' => (int) ManualSubscriptionPayment::query()->where('business_id', $business->id)->where('status', ManualSubscriptionPayment::STATUS_VERIFIED)->sum('final_amount_paisa'),
                'created_at' => $business->created_at?->toISOString(),
            ];
        })->values();

        $activities = AuditLog::query()->latest()->limit(6)->get()->map(fn (AuditLog $log): array => [
            'action' => $log->action,
            'metadata' => $log->metadata ?: [],
            'created_at' => $log->created_at?->toISOString(),
        ])->values();

        return response()->json(['data' => [
            'stats' => [
                'total_businesses' => Business::query()->count(),
                'active_businesses' => Business::query()->where('status', 'active')->count(),
                'active_owners' => User::query()->where('role', 'business-owner')->where('status', 'active')->count(),
                'subscription_revenue_paisa' => (int) $verifiedPayments->sum('final_amount_paisa'),
                'active_subscriptions' => (int) (clone $activeSubscriptions)->count(),
                'pending_payments' => (int) (clone $pendingPayments)->count(),
                'pending_amount_paisa' => (int) (clone $pendingPayments)->sum('final_amount_paisa'),
                'rejected_payments' => ManualSubscriptionPayment::query()->where('status', ManualSubscriptionPayment::STATUS_REJECTED)->count(),
                'new_owners' => User::query()->where('role', 'business-owner')->where('created_at', '>=', $now->subDays(7))->count(),
                'published_plans' => SubscriptionPlan::query()->where('status', SubscriptionPlan::STATUS_PUBLISHED)->count(),
            ],
            'revenue_trend' => $revenueTrend,
            'plan_distribution' => $plans,
            'businesses' => $businesses,
            'activities' => $activities,
        ]]);
    }

    public function settings()
    {
        return response()->json(['data' => [
            'application' => ['environment' => app()->environment(), 'timezone' => config('app.timezone'), 'billing_timezone' => config('app.business_billing_timezone', 'Asia/Dhaka')],
            'queue' => ['connection' => config('queue.default'), 'status' => config('queue.default') === 'database' ? 'ready' : 'review'],
            'email' => ['mailer' => config('mail.default'), 'status' => config('mail.default') === 'log' ? 'development-only' : 'configured'],
            'sms' => ['enabled' => (bool) config('services.mram_sms.enabled'), 'mode' => config('services.mram_sms.mode'), 'base_url' => config('services.mram_sms.base_url')],
            'payment_methods' => ManualSubscriptionPayment::METHODS,
        ], 'message' => 'Platform settings loaded successfully.']);
    }
}
