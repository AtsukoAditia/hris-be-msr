<?php

namespace Database\Seeders;

use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@company.com')->first();

        $trainings = [
            [
                'title' => 'Pelatihan Keselamatan Kerja (K3)',
                'description' => 'Pelatihan dasar keselamatan dan kesehatan kerja untuk semua karyawan. Wajib diikuti seluruh karyawan baru.',
                'category' => 'safety',
                'trainer' => 'Bpk. Hendra Wijaya, CSP',
                'location' => 'Ruang Meeting Lt. 3',
                'mode' => 'offline',
                'start_date' => now()->addDays(7)->toDateString(),
                'end_date' => now()->addDays(8)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'max_participants' => 25,
                'cost' => 0,
                'status' => 'open',
                'requirements' => 'Membawa KTP dan fotokopi Surat Keterangan Dokter',
                'created_by' => $admin?->id,
            ],
            [
                'title' => 'Leadership & Management Skills',
                'description' => 'Pelatihan untuk manager dan supervisor dalam mengelola tim secara efektif.',
                'category' => 'leadership',
                'trainer' => 'Ibu Ratna Sari, MBA',
                'location' => 'Zoom Meeting',
                'mode' => 'online',
                'start_date' => now()->addDays(14)->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '16:00',
                'max_participants' => 20,
                'cost' => 0,
                'status' => 'open',
                'created_by' => $admin?->id,
            ],
            [
                'title' => 'Digital Marketing Fundamentals',
                'description' => 'Pelatihan dasar digital marketing termasuk SEO, social media, dan content marketing.',
                'category' => 'technical',
                'trainer' => 'Tim Digital Indonesia',
                'location' => 'Aula Utama',
                'mode' => 'offline',
                'start_date' => now()->addDays(21)->toDateString(),
                'end_date' => now()->addDays(23)->toDateString(),
                'start_time' => '08:30',
                'end_time' => '16:00',
                'max_participants' => 30,
                'cost' => 500000,
                'status' => 'open',
                'created_by' => $admin?->id,
            ],
            [
                'title' => 'Kepatuhan Regululasi Terbaru',
                'description' => 'Update regulasi ketenagakerjaan dan perpajakan terbaru yang perlu dipatuhi perusahaan.',
                'category' => 'compliance',
                'trainer' => 'Kantor Akuntan Publik XYZ',
                'location' => 'Ruang Meeting Lt. 2',
                'mode' => 'hybrid',
                'start_date' => now()->addDays(5)->toDateString(),
                'end_date' => now()->addDays(5)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '12:00',
                'max_participants' => 40,
                'cost' => 0,
                'status' => 'open',
                'created_by' => $admin?->id,
            ],
            [
                'title' => 'Onboarding Program Karyawan Baru',
                'description' => 'Program orientasi dan onboarding untuk karyawan baru. Mencakup budaya perusahaan, SOP, dan tour fasilitas.',
                'category' => 'onboarding',
                'trainer' => 'Tim HRD',
                'location' => 'Kantor Pusat',
                'mode' => 'offline',
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
                'start_time' => '08:00',
                'end_time' => '17:00',
                'max_participants' => 15,
                'cost' => 0,
                'status' => 'open',
                'created_by' => $admin?->id,
            ],
        ];

        foreach ($trainings as $training) {
            Training::create($training);
        }
    }
}
