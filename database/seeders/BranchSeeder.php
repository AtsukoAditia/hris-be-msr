<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'code' => 'HQ-JKT',
                'name' => 'Head Office Jakarta',
                'address' => 'Jakarta',
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
                'radius_meters' => 150,
                'timezone' => 'Asia/Jakarta',
            ],
            [
                'code' => 'BDG',
                'name' => 'Bandung Branch',
                'address' => 'Bandung',
                'latitude' => -6.9174639,
                'longitude' => 107.6191228,
                'radius_meters' => 150,
                'timezone' => 'Asia/Jakarta',
            ],
            [
                'code' => 'SBY',
                'name' => 'Surabaya Branch',
                'address' => 'Surabaya',
                'latitude' => -7.2574719,
                'longitude' => 112.7520883,
                'radius_meters' => 150,
                'timezone' => 'Asia/Jakarta',
            ],
        ];

        foreach ($branches as $data) {
            $branch = Branch::withTrashed()->firstOrNew(['code' => $data['code']]);

            if ($branch->exists && $branch->trashed()) {
                $branch->restore();
            }

            $branch->fill($data);
            $branch->is_active = true;
            $branch->save();
        }
    }
}
