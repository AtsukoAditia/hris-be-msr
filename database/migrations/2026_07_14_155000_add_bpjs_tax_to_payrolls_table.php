<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // BPJS employer contributions (company pays)
            $table->decimal('bpjs_jkk', 15, 2)->default(0)->after('net_salary');  // Jaminan Kecelakaan Kerja
            $table->decimal('bpjs_jkm', 15, 2)->default(0)->after('bpjs_jkk');    // Jaminan Kematian
            $table->decimal('bpjs_jht_er', 15, 2)->default(0)->after('bpjs_jkm'); // Jaminan Hari Tua employer
            $table->decimal('bpjs_jp_er', 15, 2)->default(0)->after('bpjs_jht_er'); // Jaminan Pensiun employer

            // BPJS employee deductions (from salary)
            $table->decimal('bpjs_jht_ee', 15, 2)->default(0)->after('bpjs_jp_er'); // Jaminan Hari Tua employee
            $table->decimal('bpjs_jp_ee', 15, 2)->default(0)->after('bpjs_jht_ee'); // Jaminan Pensiun employee
            $table->decimal('bpjs_kes_ee', 15, 2)->default(0)->after('bpjs_jp_ee'); // Jaminan Kesehatan employee

            // Tax (PPh21)
            $table->decimal('pph21', 15, 2)->default(0)->after('bpjs_kes_ee');
            $table->boolean('tax_is_npwp')->default(false)->after('pph21'); // NPWP tersedia

            // Bank transfer
            $table->string('bank_name', 100)->nullable()->after('tax_is_npwp');
            $table->string('bank_account_number', 50)->nullable()->after('bank_name');
            $table->string('bank_account_name', 150)->nullable()->after('bank_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'bpjs_jkk', 'bpjs_jkm', 'bpjs_jht_er', 'bpjs_jp_er',
                'bpjs_jht_ee', 'bpjs_jp_ee', 'bpjs_kes_ee',
                'pph21', 'tax_is_npwp',
                'bank_name', 'bank_account_number', 'bank_account_name',
            ]);
        });
    }
};
