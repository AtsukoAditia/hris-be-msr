<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `all` is a sentinel meaning "no restriction" and is semantically equivalent
        // to NULL for filtering purposes, but accepting NULL simplifies the API and
        // makes the intent explicit at the call site
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE leave_types DROP COLUMN gender_restriction');
            DB::statement("ALTER TABLE leave_types ADD COLUMN gender_restriction VARCHAR(255) DEFAULT NULL");
            DB::statement("ALTER TABLE leave_types ADD CONSTRAINT leave_types_gender_restriction_check CHECK (gender_restriction IN ('male', 'female', 'all'))");
        } else {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->enum('gender_restriction', ['male', 'female', 'all'])
                    ->nullable()
                    ->default(null)
                    ->change();
            });
        }

        DB::table('leave_types')
            ->where('gender_restriction', 'all')
            ->update(['gender_restriction' => null]);
    }

    public function down(): void
    {
        DB::table('leave_types')
            ->whereNull('gender_restriction')
            ->update(['gender_restriction' => 'all']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE leave_types DROP CONSTRAINT IF EXISTS leave_types_gender_restriction_check');
            DB::statement('ALTER TABLE leave_types DROP COLUMN gender_restriction');
            DB::statement("ALTER TABLE leave_types ADD COLUMN gender_restriction VARCHAR(255) DEFAULT 'all' NOT NULL");
            DB::statement("ALTER TABLE leave_types ADD CONSTRAINT leave_types_gender_restriction_check CHECK (gender_restriction IN ('male', 'female', 'all'))");
        } else {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->enum('gender_restriction', ['male', 'female', 'all'])
                    ->default('all')
                    ->change();
            });
        }
    }
};
