<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscription_plan_versions', function (Blueprint $table): void {
            $table->dropForeign(['published_by_platform_admin_id']);
            $table->foreign('published_by_platform_admin_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('manual_subscription_payments', function (Blueprint $table): void {
            $table->dropForeign(['recorded_by_platform_admin_id']);
            $table->dropForeign(['verified_by_platform_admin_id']);
            $table->foreign('recorded_by_platform_admin_id')->references('id')->on('users');
            $table->foreign('verified_by_platform_admin_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('payment_verification_records', function (Blueprint $table): void {
            $table->dropForeign(['platform_admin_id']);
            $table->foreign('platform_admin_id')->references('id')->on('users');
        });
        Schema::table('subscription_lifecycle_history', function (Blueprint $table): void {
            $table->dropForeign(['platform_admin_id']);
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
