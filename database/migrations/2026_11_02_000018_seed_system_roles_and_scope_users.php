<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $roles = [
            'platform-admin' => ['dashboard.view', 'businesses.view', 'businesses.manage', 'plans.view', 'plans.manage', 'payments.view', 'payments.manage', 'reports.view', 'settings.manage'],
            'business-owner' => ['dashboard.view', 'turf.manage', 'bookings.view', 'bookings.create', 'customers.view', 'staff.manage', 'payments.view', 'reports.view', 'settings.manage'],
            'staff' => ['dashboard.view', 'turf.view', 'bookings.view', 'bookings.create', 'customers.view', 'payments.view'],
            'customer' => ['dashboard.view', 'bookings.view', 'payments.view', 'profile.manage'],
        ];

        foreach ($roles as $name => $permissions) {
            $roleId = (string) Str::ulid();
            DB::table('roles')->insertOrIgnore(['id' => $roleId, 'name' => $name, 'permissions' => json_encode($permissions), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $roleId = DB::table('roles')->where('name', $name)->whereNull('business_id')->value('id');
            DB::table('users')->where('role', $name)->update(['role_id' => $roleId]);
        }

        DB::table('business_owners')->orderBy('id')->each(function (object $membership): void {
            DB::table('users')->where('id', $membership->user_id)->update(['business_id' => $membership->business_id, 'role' => 'business-owner']);
        });
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('name', ['platform-admin', 'business-owner', 'staff', 'customer'])->delete();
    }
};
