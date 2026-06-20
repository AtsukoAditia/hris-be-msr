<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('overtime_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('overtime_date');
            $table->time('planned_start_time');
            $table->time('planned_end_time');
            $table->unsignedInteger('planned_minutes');
            $table->unsignedInteger('actual_minutes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->string('attachment')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'overtime_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};