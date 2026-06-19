<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $leaveTypes = [
            [
                'code' => 'annual',
                'name' => 'Cuti Tahunan',
                'description' => 'Cuti tahunan regular untuk karyawan tetap',
                'is_paid' => true,
                'requires_attachment' => false,
                'requires_balance' => true,
                'max_days_per_year' => 12,
                'max_consecutive_days' => null,
                'min_service_months' => 12,
                'gender_restriction' => 'all',
                'carry_forward_enabled' => false,
                'max_carry_forward_days' => null,
            ],
            [
                'code' => 'sick',
                'name' => 'Cuti Sakit',
                'description' => 'Cuti karena sakit dengan surat dokter',
                'is_paid' => true,
                'requires_attachment' => true,
                'requires_balance' => true,
                'max_days_per_year' => 14,
                'max_consecutive_days' => 14,
                'min_service_months' => 0,
                'gender_restriction' => 'all',
                'carry_forward_enabled' => false,
                'max_carry_forward_days' => null,
            ],
            [
                'code' => 'emergency',
                'name' => 'Cuti Darurat',
                'description' => 'Cuti mendadak untuk keadaan darurat keluarga',
                'is_paid' => true,
                'requires_attachment' => false,
                'requires_balance' => false,
                'max_days_per_year' => null,
                'max_consecutive_days' => 3,
                'min_service_months' => 0,
                'gender_restriction' => 'all',
                'carry_forward_enabled' => false,
                'max_carry_forward_days' => null,
            ],
            [
                'code' => 'maternity',
                'name' => 'Cuti Melahirkan',
                'description' => 'Cuti melahirkan sesuai peraturan',
                'is_paid' => true,
                'requires_attachment' => true,
                'requires_balance' => false,
                'max_days_per_year' => null,
                'max_consecutive_days' => 90,
                'min_service_months' => 0,
                'gender_restriction' => 'female',
                'carry_forward_enabled' => false,
                'max_carry_forward_days' => null,
            ],
            [
                'code' => 'paternity',
                'name' => 'Cuti Ayah',
                'description' => 'Cuti untuk ayah yang mendampingi kelahiran anak',
                'is_paid' => true,
                'requires_attachment' => true,
                'requires_balance' => false,
                'max_days_per_year' => null,
                'max_consecutive_days' => 3,
                'min_service_months' => 0,
                'gender_restriction' => 'male',
                'carry_forward_enabled' => false,
                'max_carry_forward_days' => null,
            ],
            [
                'code' => 'unpaid',
                'name' => 'Cuti Tanpa Gaji',
                'description' => 'Cuti di luar tanggungan perusahaan',
                'is_paid' => false,
                'requires_attachment' => false,
                'requires_balance' => false,
                'max_days_per_year' => null,
                'max_consecutive_days' => 30,
                'min_service_months' => 6,
                'gender_restriction' => 'all',
                'carry_forward_enabled' => false,
                'max_carry_forward_days' => null,
            ],
        ];

        foreach ($leaveTypes as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
