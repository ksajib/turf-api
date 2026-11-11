<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_salary_structures', function (Blueprint $table): void {
            $table->foreignUlid('shift_id')->nullable()->after('staff_id')->constrained('payroll_shifts')->nullOnDelete();
            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_salary_structures', function (Blueprint $table): void {
            $table->dropForeign(['shift_id']);
            $table->dropIndex(['shift_id']);
            $table->dropColumn('shift_id');
        });
    }
};
