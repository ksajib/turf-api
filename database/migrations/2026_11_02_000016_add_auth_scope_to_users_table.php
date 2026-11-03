<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'business_id')) Schema::table('users', fn (Blueprint $table) => $table->foreignUlid('business_id')->nullable()->after('id')->constrained('businesses')->nullOnDelete());
        if (! Schema::hasColumn('users', 'role_id')) Schema::table('users', fn (Blueprint $table) => $table->foreignUlid('role_id')->nullable()->after('business_id')->constrained('roles')->nullOnDelete());
        if (! Schema::hasColumn('users', 'permissions')) Schema::table('users', fn (Blueprint $table) => $table->json('permissions')->nullable()->after('role_id'));
        if (! Schema::hasColumn('users', 'role')) Schema::table('users', fn (Blueprint $table) => $table->string('role', 40)->nullable()->after('permissions')->index());
        if (! Schema::hasColumn('users', 'status')) Schema::table('users', fn (Blueprint $table) => $table->string('status', 20)->default('active')->after('role')->index());
        if (! Schema::hasColumn('users', 'token_reference')) Schema::table('users', fn (Blueprint $table) => $table->string('token_reference', 100)->nullable()->after('status')->index());
        if (! Schema::hasColumn('users', 'last_login_at')) Schema::table('users', fn (Blueprint $table) => $table->timestampTz('last_login_at')->nullable()->after('password_changed_at'));
        if (! Schema::hasColumn('users', 'deleted_at')) Schema::table('users', fn (Blueprint $table) => $table->softDeletesTz());
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IS NULL OR role IN ('platform-admin', 'business-owner', 'staff', 'customer'))");
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
