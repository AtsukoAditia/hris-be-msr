<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankExportService
{
    public const FORMAT_CSV = 'csv';
    public const FORMAT_XLS = 'xls'; // simplified tab-delimited

    /**
     * Generate bank transfer file for finalized payroll period.
     * Returns StreamedResponse for direct download.
     */
    public function generate(
        PayrollPeriod $period,
        Collection $payrolls,
        string $format = self::FORMAT_CSV,
        string $companyBankAccount = '',
    ): StreamedResponse {
        $filename = "transfer_gaji_{$period->name}_" . now()->format('Ymd_His') . ".{$format}";

        $headers = ['No', 'No. Rekening', 'Nama Pemilik', 'Bank', 'Jumlah (IDR)', 'Keterangan'];
        $rows = $payrolls->map(fn (Payroll $p) => [
            $p->employee?->id ?? '',
            $p->bank_account_number ?? '',
            $p->bank_account_name ?? '',
            $p->bank_name ?? '',
            number_format((float) $p->net_salary, 2, '.', ''),
            "Gaji {$period->name}",
        ])->toArray();

        $callback = function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Return column definitions for UI preview table.
     */
    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No'],
            ['key' => 'employee_id', 'label' => 'ID Karyawan'],
            ['key' => 'employee_name', 'label' => 'Nama Karyawan'],
            ['key' => 'bank_account_number', 'label' => 'No. Rekening'],
            ['key' => 'bank_name', 'label' => 'Bank'],
            ['key' => 'bank_account_name', 'label' => 'Nama Pemilik'],
            ['key' => 'net_salary', 'label' => 'Jumlah (IDR)'],
        ];
    }
}
