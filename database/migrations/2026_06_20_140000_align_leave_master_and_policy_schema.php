<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_types', 'requires_balance')) {
                $table->boolean('requires_balance')->default(true)->after('gender_restriction');
            }
            if (! Schema::hasColumn('leave_types', 'max_days_per_year')) {
                $table->unsignedSmallInteger('max_days_per_year')->default(0)->after('requires_balance');
            }
            if (! Schema::hasColumn('leave_types', 'default_days_per_year')) {
                $table->unsignedSmallInteger('default_days_per_year')
                    ->default(0)
                    ->after('max_days_per_year');
            }
            if (! Schema::hasColumn('leave_types', 'min_service_months')) {
                $table->unsignedSmallInteger('min_service_months')->default(0)->after('max_consecutive_days');
            }
            if (! Schema::hasColumn('leave_types', 'carry_forward_enabled')) {
                $table->boolean('carry_forward_enabled')->default(false)->after('gender_restriction');
            }
            if (! Schema::hasColumn('leave_types', 'max_carry_forward_days')) {
                $table->unsignedSmallInteger('max_carry_forward_days')->nullable()->after('carry_forward_enabled');
            }
        });

        Schema::table('holidays', function (Blueprint $table) {
            if (! Schema::hasColumn('holidays', 'type')) {
                $table->string('type', 32)->default('national')->after('date');
            }
            if (! Schema::hasColumn('holidays', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
        });

        Schema::table('leave_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_policies', 'policy_name')) {
                $table->string('policy_name')->nullable()->after('leave_type_id');
            }
            if (! Schema::hasColumn('leave_policies', 'min_service_months')) {
                $table->unsignedSmallInteger('min_service_months')->default(0)->after('default_quota');
            }
            if (! Schema::hasColumn('leave_policies', 'accrual_type')) {
                $table->string('accrual_type', 32)->default('yearly')->after('min_service_months');
            }
            if (! Schema::hasColumn('leave_policies', 'accrual_amount')) {
                $table->unsignedSmallInteger('accrual_amount')->default(0)->after('accrual_type');
            }
            if (! Schema::hasColumn('leave_policies', 'max_carry_forward_days')) {
                $table->unsignedSmallInteger('max_carry_forward_days')->nullable()->after('accrual_amount');
            }
            if (! Schema::hasColumn('leave_policies', 'carry_forward_expiry_months')) {
                $table->unsignedTinyInteger('carry_forward_expiry_months')->nullable()->after('max_carry_forward_days');
            }

            $table->index('leave_type_id', 'leave_policies_leave_type_id_index');
            $table->dropUnique(['leave_type_id', 'year']);
            $table->unique(['leave_type_id', 'year', 'policy_name'], 'leave_policies_leave_type_year_policy_unique');
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_balances', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('remaining_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            if (Schema::hasColumn('leave_balances', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('leave_policies', function (Blueprint $table) {
            $table->dropUnique('leave_policies_leave_type_year_policy_unique');
            $table->dropIndex('leave_policies_leave_type_id_index');
            $table->unique(['leave_type_id', 'year']);

            foreach ([
                'policy_name',
                'min_service_months',
                'accrual_type',
                'accrual_amount',
                'max_carry_forward_days',
                'carry_forward_expiry_months',
            ] as $column) {
                if (Schema::hasColumn('leave_policies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('holidays', function (Blueprint $table) {
            if (Schema::hasColumn('holidays', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('holidays', 'type')) {
                $table->dropColumn('type');
            }
        });

        Schema::table('leave_types', function (Blueprint $table) {
            foreach ([
                'requires_balance',
                'max_days_per_year',
                'default_days_per_year',
                'min_service_months',
                'carry_forward_enabled',
                'max_carry_forward_days',
            ] as $column) {
                if (Schema::hasColumn('leave_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
