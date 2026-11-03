<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AuthenticationTokenService;
use App\Services\AuditLogger;
use App\Services\UserProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(Request $request, UserProvisioningService $provisioning, AuthenticationTokenService $tokenService, AuditLogger $auditLogger)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', 'unique:users,username'],
            'phone' => ['required', 'string', 'max:40', 'unique:users,phone'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        $customer = DB::transaction(function () use ($data, $provisioning): Customer {
            $created = $provisioning->create(['name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'], 'phone' => $data['phone'], 'password' => $data['password']], 'customer');
            return Customer::create(['user_id' => $created['user']->id, 'status' => 'active']);
        });

        $customer->load('user');
        $user = $customer->user;
        $auditLogger->record('customer.registered', $user, $request, ['customer_id' => $customer->id]);
        return $tokenService->addCookies(response()->json(['data' => ['role' => 'customer', 'requires_password_change' => false, 'redirect_to' => '/dashboard', 'profile' => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'email' => $user->email]], 'message' => 'Customer account created successfully.'], 201), $tokenService->issue($user));
    }
}
