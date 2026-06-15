<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            [
                'department_code' => 'MGT',
                'code' => 'SYS-ADMIN',
                'name' => 'System Administrator',
                'description' => 'Mengelola konfigurasi, keamanan, dan operasional sistem HRIS.',
            ],
            [
                'department_code' => 'HR',
                'code' => 'HR-STAFF',
                'name' => 'HR Staff',
                'description' => 'Menangani administrasi dan operasional sumber daya manusia.',
            ],
            [
                'department_code' => 'OPS',
                'code' => 'OPS-MANAGER',
                'name' => 'Operational Manager',
                'description' => 'Mengelola aktivitas dan target operasional perusahaan.',
            ],
            [
                'department_code' => 'OPS',
                'code' => 'OPS-STAFF',
                'name' => 'Staff Operation',
                'description' => 'Menjalankan kegiatan operasional harian.',
            ],
            [
                'department_code' => 'IT',
                'code' => 'SOFTWARE-ENGINEER',
                'name' => 'Software Engineer',
                'description' => 'Mengembangkan dan memelihara aplikasi perusahaan.',
            ],
            [
                'department_code' => 'FIN',
                'code' => 'FIN-STAFF',
                'name' => 'Finance Staff',
                'description' => 'Menangani administrasi dan pelaporan keuangan.',
            ],
            [
                'department_code' => 'MKT',
                'code' => 'MKT-STAFF',
                'name' => 'Marketing Staff',
                'description' => 'Menangani pemasaran dan komunikasi perusahaan.',
            ],
        ];

        foreach ($positions as $data) {
            $departmentId = Department::where('code', $data['department_code'])->value('id');

            if (! $departmentId) {
                continue;
            }

            $position = Position::withTrashed()->firstOrNew([
                'code' => $data['code'],
            ]);

            if ($position->exists && $position->trashed()) {
                $position->restore();
            }

            $position->fill([
                'department_id' => $departmentId,
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'],
                'is_active' => true,
            ])->save();
        }
    }
}
