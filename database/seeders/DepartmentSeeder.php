<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            [
                'code' => 'IT',
                'name' => 'Information Technology',
                'description' => 'Mengelola sistem, aplikasi, infrastruktur, dan dukungan teknologi perusahaan.',
            ],
            [
                'code' => 'HR',
                'name' => 'Human Resources',
                'description' => 'Mengelola administrasi, pengembangan, dan hubungan karyawan.',
            ],
            [
                'code' => 'FIN',
                'name' => 'Finance',
                'description' => 'Mengelola keuangan, anggaran, pembayaran, dan pelaporan perusahaan.',
            ],
            [
                'code' => 'OPS',
                'name' => 'Operations',
                'description' => 'Mengelola kegiatan operasional dan proses bisnis harian.',
            ],
            [
                'code' => 'MGT',
                'name' => 'Management',
                'description' => 'Mengelola arah strategis, tata kelola, dan pengambilan keputusan perusahaan.',
            ],
            [
                'code' => 'MKT',
                'name' => 'Marketing',
                'description' => 'Mengelola pemasaran, komunikasi, promosi, dan pertumbuhan pasar.',
            ],
        ];

        foreach ($departments as $data) {
            $department = Department::withTrashed()->firstOrNew([
                'code' => $data['code'],
            ]);

            if ($department->exists && $department->trashed()) {
                $department->restore();
            }

            $department->fill([
                ...$data,
                'is_active' => true,
            ])->save();
        }
    }
}
