<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        DB::table('platform_admins')->orderBy('id')->each(function (object $admin): void {
            if (DB::table('users')->where('email', $admin->email)->exists()) return;
            DB::table('users')->insert([
                'id' => (string) Str::ulid(), 'name' => $admin->name, 'username' => $admin->username ?? null,
                'email' => $admin->email, 'password' => $admin->password, 'role' => 'platform-admin',
                'status' => $admin->is_active ? 'active' : 'inactive', 'last_login_at' => $admin->last_login_at,
                'created_at' => $admin->created_at, 'updated_at' => $admin->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'platform-admin')->delete();
    }
};
