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
        // makes the intent explicit at the call site.
        Schema::table('leave_types', function (Blueprint $table) {
            $table->enum('gender_restriction', ['male', 'female', 'all'])
                ->nullable()
                ->default(null)
                ->change();
        });

        DB::table('leave_types')
            ->where('gender_restriction', 'all')
            ->update(['gender_restriction' => null]);
    }

    public function down(): void
    {
        DB::table('leave_types')
            ->whereNull('gender_restriction')
            ->update(['gender_restriction' => 'all']);

        Schema::table('leave_types', function (Blueprint $table) {
            $table->enum('gender_restriction', ['male', 'female', 'all'])
                ->default('all')
                ->change();
        });
    }
};
