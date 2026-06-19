<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Align leave_balances column names with the convention used by LeaveService.
 *
 * - opening_balance  -> opening_days
 * - used_days        (unchanged)
 * - remaining_balance -> remaining_days
 *
 * This avoids touching the original create migration while keeping service
 * code readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->renameColumn('opening_balance', 'opening_days');
            $table->renameColumn('remaining_balance', 'remaining_days');
        });
    }

    public function down(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->renameColumn('opening_days', 'opening_balance');
            $table->renameColumn('remaining_days', 'remaining_balance');
        });
    }
};
