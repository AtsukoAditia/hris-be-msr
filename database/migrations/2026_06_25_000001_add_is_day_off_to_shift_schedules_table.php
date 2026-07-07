<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->boolean('is_day_off')->default(false)->after('schedule_date');
            $table->index(['schedule_date', 'is_day_off']);
        });
    }

    public function down(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropIndex(['schedule_date', 'is_day_off']);
            $table->dropColumn('is_day_off');
        });
    }
};
