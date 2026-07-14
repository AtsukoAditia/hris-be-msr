<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // Per-field change tracking
            $table->json('old_values')->nullable()->after('response_payload');
            $table->json('new_values')->nullable()->after('old_values');
            // Target entity (employee_id, leave_id, etc.)
            $table->string('target_type', 100)->nullable()->after('module');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->index(['target_type', 'target_id'], 'activity_logs_target_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_target_index');
            $table->dropColumn(['old_values', 'new_values', 'target_type', 'target_id']);
        });
    }
};
