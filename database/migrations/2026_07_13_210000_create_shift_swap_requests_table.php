<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shift_swap_requests')) {
            Schema::create('shift_swap_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('requester_id')->constrained('employees')->onDelete('cascade');
                $table->foreignId('target_id')->constrained('employees')->onDelete('cascade');
                $table->foreignId('requester_schedule_id')->constrained('shift_schedules')->onDelete('cascade');
                $table->foreignId('target_schedule_id')->nullable()->constrained('shift_schedules')->onDelete('set null');
                $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
                $table->text('reason')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
                $table->text('review_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['requester_id', 'status']);
                $table->index(['target_id', 'status']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
    }
};
