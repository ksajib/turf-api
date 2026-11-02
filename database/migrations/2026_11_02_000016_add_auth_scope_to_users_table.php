<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUlid('business_id')->nullable()->after('id')->constrained('businesses')->nullOnDelete();
            $table->foreignUlid('role_id')->nullable()->after('business_id')->constrained('roles')->nullOnDelete();
            $table->json('permissions')->nullable()->after('role_id');
            $table->string('role', 40)->nullable()->after('permissions')->index();
            $table->string('status', 20)->default('active')->after('role')->index();
            $table->string('token_reference', 100)->nullable()->after('status')->index();
            $table->timestampTz('last_login_at')->nullable()->after('password_changed_at');
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletesTz();
            $table->dropForeign(['business_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn(['business_id', 'role_id', 'permissions', 'role', 'status', 'token_reference', 'last_login_at']);
        });
    }
};
