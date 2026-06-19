<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->foreignId('leave_type_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
        });

        // Map existing leave_type enum values to leave_types table
        $typeMapping = [
            'annual' => 'Cuti Tahunan',
            'sick' => 'Cuti Sakit',
            'emergency' => 'Cuti Darurat',
            'maternity' => 'Cuti Melahirkan',
            'paternity' => 'Cuti Ayah',
            'unpaid' => 'Cuti Tanpa Gaji',
            'other' => 'Lainnya',
        ];

        foreach ($typeMapping as $code => $name) {
            $leaveType = DB::table('leave_types')->where('code', $code)->first();
            if ($leaveType) {
                DB::table('leaves')
                    ->where('leave_type', $code)
                    ->update(['leave_type_id' => $leaveType->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['leave_type_id']);
            $table->dropColumn('leave_type_id');
        });
    }
};
