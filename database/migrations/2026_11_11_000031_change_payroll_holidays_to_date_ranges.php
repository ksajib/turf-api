<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_holidays', function (Blueprint $table): void {
            $table->date('from_date')->nullable()->after('gym_id');
            $table->date('to_date')->nullable()->after('from_date');
            $table->dropUnique(['gym_id', 'holiday_date']);
        });

        DB::statement('UPDATE payroll_holidays SET from_date = holiday_date, to_date = holiday_date');

        DB::statement('ALTER TABLE payroll_holidays ALTER COLUMN from_date SET NOT NULL');
        DB::statement('ALTER TABLE payroll_holidays ALTER COLUMN to_date SET NOT NULL');

        Schema::table('payroll_holidays', function (Blueprint $table): void {
            $table->unique(['gym_id', 'from_date', 'to_date']);
            $table->dropColumn('holiday_date');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_holidays', function (Blueprint $table): void {
            $table->date('holiday_date')->nullable()->after('gym_id');
            $table->dropUnique(['gym_id', 'from_date', 'to_date']);
        });

        DB::statement('UPDATE payroll_holidays SET holiday_date = from_date');

        DB::statement('ALTER TABLE payroll_holidays ALTER COLUMN holiday_date SET NOT NULL');

        Schema::table('payroll_holidays', function (Blueprint $table): void {
            $table->unique(['gym_id', 'holiday_date']);
            $table->dropColumn(['from_date', 'to_date']);
        });
    }
};
