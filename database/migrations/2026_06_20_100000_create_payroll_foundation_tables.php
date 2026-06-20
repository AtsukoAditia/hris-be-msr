<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->enum('type', ['earning', 'deduction']);
            $table->enum('calculation_type', ['fixed', 'percentage', 'formula'])->default('fixed');
            $table->decimal('default_amount', 15, 2)->default(0);
            $table->decimal('percentage', 8, 4)->nullable();
            $table->text('formula')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
        });

        Schema::create('employee_salary_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 15, 2);
            $table->string('currency', 3)->default('IDR');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'effective_from']);
            $table->index(['employee_id', 'is_active', 'effective_from', 'effective_to'], 'salary_profile_effective_idx');
        });

        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_salary_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->text('formula')->nullable();
            $table->timestamps();

            $table->unique(['employee_salary_profile_id', 'salary_component_id'], 'salary_profile_component_unique');
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('cutoff_start_date');
            $table->date('cutoff_end_date');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();

            $table->unique(['start_date', 'end_date']);
            $table->index(['status', 'end_date']);
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_salary_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'reviewed', 'finalized', 'paid', 'cancelled'])->default('draft');
            $table->string('currency', 3)->default('IDR');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('total_earnings', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->unsignedInteger('attendance_days')->default(0);
            $table->unsignedInteger('absent_days')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('unpaid_leave_days')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->json('input_snapshot')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index(['status', 'payroll_period_id']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 120);
            $table->enum('type', ['earning', 'deduction']);
            $table->string('source', 50);
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('rate', 15, 4)->nullable();
            $table->decimal('amount', 15, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payroll_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('employee_salary_profiles');
        Schema::dropIfExists('salary_components');
    }
};
