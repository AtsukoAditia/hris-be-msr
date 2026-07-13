<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add status and conflict fields to shift_schedules
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('notes')
                ->comment('draft, published, approved, rejected, cancelled');
            $table->string('conflict_type')->nullable()->after('status')
                ->comment('rest_hour, max_hours, overlap, coverage');
            $table->string('conflict_message')->nullable()->after('conflict_type');
            $table->integer('version')->default(1)->after('conflict_message');
            $table->foreignId('published_by')->nullable()->after('version')
                ->constrained('users')->onDelete('set null');
            $table->timestamp('published_at')->nullable()->after('published_by');
            $table->index(['status']);
            $table->index(['status', 'schedule_date']);
        });

        // Create shift_swap_requests table
        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('target_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('requester_schedule_id')->constrained('shift_schedules')->onDelete('cascade');
            $table->foreignId('target_schedule_id')->nullable()->constrained('shift_schedules')->onDelete('set null');
            $table->string('status')->default('pending')
                ->comment('pending, approved, rejected, cancelled');
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['requester_id', 'status']);
            $table->index(['target_id', 'status']);
        });

        // Create shift_schedule_versions table
        Schema::create('shift_schedule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_schedule_id')->constrained()->onDelete('cascade');
            $table->integer('version');
            $table->json('changes');
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action')->comment('created, updated, published, unpublished');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shift_schedule_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_schedule_versions');
        Schema::dropIfExists('shift_swap_requests');
        Schema::table('shift_schedules', function (Blueprint $table) {
            $table->dropIndex(['status', 'schedule_date']);
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'conflict_type', 'conflict_message', 'version', 'published_by', 'published_at']);
        });
    }
};
