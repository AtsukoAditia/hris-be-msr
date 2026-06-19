<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert leave_type from ENUM to STRING so it can hold the
        // dynamic code from leave_types (e.g. "ANNUAL", "CUTI_TAHUNAN").
        // Existing values are preserved by MySQL when the column widens.
        Schema::table('leaves', function (Blueprint $table) {
            $table->string('leave_type', 50)->change();
        });
    }

    public function down(): void
    {
        // Narrow back to the original ENUM. Values that no longer fit
        // would need a data migration first; we only support narrowing
        // when data is compatible.
        Schema::table('leaves', function (Blueprint $table) {
            $table->enum('leave_type', ['annual', 'sick', 'emergency', 'maternity', 'paternity', 'unpaid', 'other'])
                ->change();
        });
    }
};
