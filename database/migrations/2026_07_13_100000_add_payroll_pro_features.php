<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('status');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL: drop old check constraint, alter column type, add new constraint
            DB::statement('ALTER TABLE payrolls DROP CONSTRAINT IF EXISTS payrolls_status_check');
            DB::statement("ALTER TABLE payrolls ALTER COLUMN status TYPE varchar(255)");
            DB::statement("ALTER TABLE payrolls ALTER COLUMN status SET DEFAULT 'draft'");
            DB::statement("ALTER TABLE payrolls ALTER COLUMN status SET NOT NULL");
            DB::statement("ALTER TABLE payrolls ADD CONSTRAINT payrolls_status_check CHECK (status IN ('draft', 'submitted', 'reviewed', 'approved', 'finalized', 'paid', 'cancelled'))");
        } else {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->enum('status', ['draft', 'submitted', 'reviewed', 'approved', 'finalized', 'paid', 'cancelled'])->default('draft')->change();
            });
        }

        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->after('generated_at')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('approved_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            if (DB::getDriverName() === 'pgsql') {
                $table->string('type', 20);
            } else {
                $table->enum('type', ['earning', 'deduction']);
            }
            $table->string('code', 50);
            $table->string('name', 120);
            $table->decimal('amount', 15, 2);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payroll_id', 'type']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE payroll_adjustments ADD CONSTRAINT payroll_adjustments_type_check CHECK (type IN ('earning', 'deduction'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropColumn(['submitted_by', 'submitted_at']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_by', 'approved_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payrolls DROP CONSTRAINT IF EXISTS payrolls_status_check');
            DB::statement("ALTER TABLE payrolls ALTER COLUMN status TYPE varchar(255)");
            DB::statement("ALTER TABLE payrolls ADD CONSTRAINT payrolls_status_check CHECK (status IN ('draft', 'reviewed', 'finalized', 'paid', 'cancelled'))");
        } else {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->enum('status', ['draft', 'reviewed', 'finalized', 'paid', 'cancelled'])->default('draft')->change();
            });
        }

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['locked_at', 'locked_by']);
        });
    }
};
