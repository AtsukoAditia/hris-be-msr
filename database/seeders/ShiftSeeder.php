<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            [
                'name' => 'Shift Pagi',
                'code' => 'S1',
                'start_time' => '07:00:00',
                'end_time' => '16:00:00',
                'late_tolerance' => 15,
                'is_overnight' => false,
                'is_active' => true,
                'description' => 'Shift pagi reguler 07:00 - 16:00',
            ],
            [
                'name' => 'Shift Siang',
                'code' => 'S2',
                'start_time' => '12:00:00',
                'end_time' => '21:00:00',
                'late_tolerance' => 15,
                'is_overnight' => false,
                'is_active' => true,
                'description' => 'Shift siang reguler 12:00 - 21:00',
            ],
            [
                'name' => 'Shift Malam',
                'code' => 'S3',
                'start_time' => '21:00:00',
                'end_time' => '06:00:00',
                'late_tolerance' => 15,
                'is_overnight' => true,
                'is_active' => true,
                'description' => 'Shift malam 21:00 - 06:00 (overnight)',
            ],
            [
                'name' => 'Normal Office Hours',
                'code' => 'OH',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'late_tolerance' => 30,
                'is_overnight' => false,
                'is_active' => true,
                'description' => 'Jam kerja kantor normal 08:00 - 17:00',
            ],
        ];

        foreach ($shifts as $shift) {
            Shift::firstOrCreate(['code' => $shift['code']], $shift);
        }
    }
}
