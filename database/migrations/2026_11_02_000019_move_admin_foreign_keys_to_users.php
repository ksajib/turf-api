<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscription_plan_versions', fn (Blueprint $table) => $table->dropForeign(['published_by_platform_admin_id']));
        Schema::table('manual_subscription_payments', function (Blueprint $table): void {
            $table->dropForeign(['recorded_by_platform_admin_id']);
            $table->dropForeign(['verified_by_platform_admin_id']);
        });
        Schema::table('payment_verification_records', fn (Blueprint $table) => $table->dropForeign(['platform_admin_id']));
        Schema::table('subscription_lifecycle_history', fn (Blueprint $table) => $table->dropForeign(['platform_admin_id']));

        DB::table('platform_admins')->orderBy('id')->each(function (object $admin): void {
            $userId = DB::table('users')->where('email', $admin->email)->value('id');
            if (! $userId) {
                DB::table('users')->insert([
                    'id' => $admin->id, 'name' => $admin->name, 'username' => $admin->username ?? null,
                    'email' => $admin->email, 'password' => $admin->password, 'role' => 'platform-admin',
                    'status' => $admin->is_active ? 'active' : 'inactive', 'last_login_at' => $admin->last_login_at,
                    'created_at' => $admin->created_at, 'updated_at' => $admin->updated_at,
                ]);
                return;
            }
            foreach ([
                ['table' => 'subscription_plan_versions', 'column' => 'published_by_platform_admin_id'],
                ['table' => 'manual_subscription_payments', 'column' => 'recorded_by_platform_admin_id'],
                ['table' => 'manual_subscription_payments', 'column' => 'verified_by_platform_admin_id'],
                ['table' => 'payment_verification_records', 'column' => 'platform_admin_id'],
                ['table' => 'subscription_lifecycle_history', 'column' => 'platform_admin_id'],
            ] as $reference) {
                DB::table($reference['table'])->where($reference['column'], $admin->id)->update([$reference['column'] => $userId]);
            }
        });

        Schema::table('subscription_plan_versions', function (Blueprint $table): void {
            $table->foreign('published_by_platform_admin_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('manual_subscription_payments', function (Blueprint $table): void {
            $table->foreign('recorded_by_platform_admin_id')->references('id')->on('users');
            $table->foreign('verified_by_platform_admin_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('payment_verification_records', function (Blueprint $table): void {
            $table->foreign('platform_admin_id')->references('id')->on('users');
        });
        Schema::table('subscription_lifecycle_history', function (Blueprint $table): void {
            $table->foreign('platform_admin_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plan_versions', function (Blueprint $table): void {
            $table->dropForeign(['published_by_platform_admin_id']);
            $table->foreign('published_by_platform_admin_id')->references('id')->on('platform_admins')->nullOnDelete();
        });
        Schema::table('manual_subscription_payments', function (Blueprint $table): void {
            $table->dropForeign(['recorded_by_platform_admin_id']);
            $table->dropForeign(['verified_by_platform_admin_id']);
            $table->foreign('recorded_by_platform_admin_id')->references('id')->on('platform_admins');
            $table->foreign('verified_by_platform_admin_id')->references('id')->on('platform_admins')->nullOnDelete();
        });
        Schema::table('payment_verification_records', function (Blueprint $table): void {
            $table->dropForeign(['platform_admin_id']);
            $table->foreign('platform_admin_id')->references('id')->on('platform_admins');
        });
        Schema::table('subscription_lifecycle_history', function (Blueprint $table): void {
            $table->dropForeign(['platform_admin_id']);
            $table->foreign('platform_admin_id')->references('id')->on('platform_admins')->nullOnDelete();
        });
    }
};
