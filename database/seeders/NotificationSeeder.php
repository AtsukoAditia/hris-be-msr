<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@company.com')->first();
        $hr = User::where('email', 'hr@company.com')->first();
        $manager = User::where('email', 'manager@company.com')->first();
        $employee = User::where('email', 'employee@company.com')->first();

        if (!$admin) return;

        // Admin gets some notifications
        NotificationService::create($admin->id, 'leave_requested', 'Pengajuan Cuti Baru', 'Andi Pratama mengajukan cuti tahun 12 Jul — 14 Jul.', '📋', '/approval');
        NotificationService::create($admin->id, 'shift_swap_requested', 'Permintaan Tukar Shift', 'Budi Santoso mengajukan tukar shift dengan Andi.', '🔄', '/approval');
        NotificationService::create($admin->id, 'profile_change_requested', 'Perubahan Profil', 'Andi Pratama mengajukan perubahan alamat.', '👤', '/profile-change-reviews');

        // Manager gets notifications
        if ($manager) {
            NotificationService::create($manager->id, 'leave_requested', 'Pengajuan Cuti Baru', 'Andi Pratama mengajukan cuti tahun.', '📋', '/approval');
        }

        // Employee gets approval notification
        if ($employee) {
            NotificationService::create($employee->id, 'leave_approved', 'Cuti Disetujui', 'Pengajuan cuti tahun Anda telah disetujui.', '✅', '/leave');
            NotificationService::create($employee->id, 'shift_published', 'Jadwal Shift Dipublish', 'Jadwal shift minggu depan telah dipublish.', '📅', '/my-schedule');
            NotificationService::create($employee->id, 'leave_balance_updated', 'Saldo Cuti Diperbarui', 'Saldo cuti tahun Anda tersisa 8 hari.', '💰', '/leave');
        }
    }
}
