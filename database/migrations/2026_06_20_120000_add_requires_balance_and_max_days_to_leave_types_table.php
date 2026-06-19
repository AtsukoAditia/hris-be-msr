<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_types', 'requires_balance')) {
                $table->boolean('requires_balance')->default(true)->after('requires_attachment');
            }
            if (! Schema::hasColumn('leave_types', 'max_days_per_year')) {
                $table->integer('max_days_per_year')->nullable()->after('max_consecutive_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (Schema::hasColumn('leave_types', 'requires_balance')) {
                $table->dropColumn('requires_balance');
            }
            if (Schema::hasColumn('leave_types', 'max_days_per_year')) {
                $table->dropColumn('max_days_per_year');
            }
        });
    }
};
