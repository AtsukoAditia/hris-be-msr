<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('shift_schedules', 'status')) {
                $table->string('status')->default('draft')->after('notes');
            }
            if (! Schema::hasColumn('shift_schedules', 'conflict_type')) {
                $table->string('conflict_type')->nullable()->after('status');
            }
            if (! Schema::hasColumn('shift_schedules', 'conflict_message')) {
                $table->text('conflict_message')->nullable()->after('conflict_type');
            }
            if (! Schema::hasColumn('shift_schedules', 'version')) {
                $table->integer('version')->default(1)->after('conflict_message');
            }
            if (! Schema::hasColumn('shift_schedules', 'published_by')) {
                $table->foreignId('published_by')->nullable()->after('version');
            }
            if (! Schema::hasColumn('shift_schedules', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('published_by');
            }
        });

        // Add foreign key + indexes with try-catch for idempotency
        try {
            Schema::table('shift_schedules', function (Blueprint $table) {
                $table->foreign('published_by')->references('id')->on('users')->onDelete('set null');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('shift_schedules', function (Blueprint $table) {
                $table->index('status');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('shift_schedules', function (Blueprint $table) {
                $table->index('published_at');
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['published_at']);
            $table->dropForeign(['published_by']);
            $table->dropColumn(['status', 'conflict_type', 'conflict_message', 'version', 'published_by', 'published_at']);
        });
    }
};
