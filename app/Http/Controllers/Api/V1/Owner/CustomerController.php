<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\UserProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    private function authorize(Request $request, string $permission = 'customers.view'): void
    {
        $user = $request->user();
        abort_unless($user && ($user->role === 'business-owner' || $user->hasPermission($permission)), 403, 'You do not have permission to manage customers.');
    }

    private function present(Customer $customer): array
    {
        $customer->loadMissing('user');
        return ['id' => $customer->id, 'status' => $customer->status, 'user_id' => $customer->user_id, 'name' => $customer->user?->name, 'username' => $customer->user?->username, 'phone' => $customer->user?->phone, 'email' => $customer->user?->email, 'booking_count' => (int) ($customer->bookings_count ?? 0), 'total_spent_taka' => (int) ($customer->bookings_sum_price_taka ?? 0), 'last_booking_at' => $customer->bookings_max_created_at ? \Illuminate\Support\Carbon::parse($customer->bookings_max_created_at)->toISOString() : null, 'created_at' => $customer->created_at?->toISOString()];
    }

    public function index(Request $request)
    {
        $this->authorize($request);
        $query = Customer::query()->with('user')->withCount('bookings')->withSum('bookings', 'price_taka')->withMax('bookings', 'created_at');
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->whereHas('user', fn ($user) => $user->where('name', 'ilike', "%{$search}%")->orWhere('username', 'ilike', "%{$search}%")->orWhere('phone', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"));
        }
        $customers = $query->latest()->get();
        return response()->json(['data' => $customers->map(fn (Customer $customer) => $this->present($customer))->values(), 'meta' => ['total' => $customers->count(), 'active' => $customers->where('status', 'active')->count(), 'repeat' => $customers->filter(fn (Customer $customer) => (int) ($customer->bookings_count ?? 0) >= 4)->count()]]);
    }

    public function store(Request $request, UserProvisioningService $provisioning)
    {
        $this->authorize($request, 'customers.create');
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:160'], 'phone' => ['required', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:255']]);
        $base = Str::of($data['name'])->lower()->replaceMatches('/[^a-z0-9]+/', '.')->trim('.')->substr(0, 38)->toString() ?: 'customer';
        $username = $base;
        for ($suffix = 1; User::where('username', $username)->exists(); $suffix++) $username = substr($base, 0, 45 - strlen((string) $suffix) - 1).'.'.$suffix;
        $email = $data['email'] ?? ($username.'.'.str()->random(6).'@customer.invalid');
        $created = $provisioning->create(['name' => $data['name'], 'username' => $username, 'email' => $email, 'phone' => $data['phone']], 'customer', null, true);
        $customer = Customer::create(['user_id' => $created['user']->id, 'status' => 'active']);
        return response()->json(['data' => $this->present($customer), 'message' => 'Customer created successfully.'], 201);
    }
}
