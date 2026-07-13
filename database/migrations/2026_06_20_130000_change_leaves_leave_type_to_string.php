<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert leave_type from ENUM to STRING so it can hold the
        // dynamic code from leave_types (e.g. "ANNUAL", "CUTI_TAHUNAN").
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL: drop check constraint first, then cast enum to varchar
            DB::statement('ALTER TABLE leaves DROP CONSTRAINT IF EXISTS leaves_leave_type_check');
            DB::statement('ALTER TABLE leaves ALTER COLUMN leave_type TYPE VARCHAR(50) USING leave_type::VARCHAR');
        } else {
            Schema::table('leaves', function (Blueprint $table) {
                $table->string('leave_type', 50)->change();
            });
        }
    }

    public function down(): void
    {
        // Narrow back to the original ENUM. Values that no longer fit
        // would need a data migration first; we only support narrowing
        // when data is compatible.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE leaves ALTER COLUMN leave_type TYPE VARCHAR(20)");
            DB::statement("ALTER TABLE leaves ALTER COLUMN leave_type SET DEFAULT 'annual'");
            DB::statement("ALTER TABLE leaves ALTER COLUMN leave_type SET NOT NULL");
            DB::statement("ALTER TABLE leaves ADD CONSTRAINT leaves_leave_type_check CHECK (leave_type IN ('annual', 'sick', 'emergency', 'maternity', 'paternity', 'unpaid', 'other'))");
        } else {
            Schema::table('leaves', function (Blueprint $table) {
                $table->enum('leave_type', ['annual', 'sick', 'emergency', 'maternity', 'paternity', 'unpaid', 'other'])
                    ->change();
            });
        }
    }
};
